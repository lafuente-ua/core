<?
namespace core\config;
use core\db;

class collection extends \core\collection
{
	protected static $_model_class    = __NAMESPACE__.'\model';
	protected static $_model_table    = 'config';
	protected static $_autosingleton  = true;

	protected $_config_data = [];

	protected function _readAllData()
	{
		$this->_config_data = [];
		foreach($this->unload()
			->addWhere('!`locked`')
			->getAllItems() as $data)
					$this->_config_data[$data->getName()] = $data->getValue();
		if(self::$_redis)
			self::$_redis->setEx(db::REDIS_CONFIG,db::REDIS_CONFIG_TTL,serialize($this->_config_data));
		return $this;
	}
	public function readAllData()
	{
		if(self::$_redis?->exists(db::REDIS_CONFIG))
			$this->_config_data = unserialize(self::$_redis->get(db::REDIS_CONFIG));
		else
			$this->_readAllData();
		return $this;
	}
	public function getByName($name)
	{
		if(!count($this->_config_data))
			$this->readAllData();
		return $this->_config_data[$name];
	}
	public function storeByName($name, $value)
	{
		(model::get())->setName($name)
			->setValue($value)
			->save();
		return $this->uncache();
	}
	public function unCache()
	{
		self::$_redis?->unlink(db::REDIS_CONFIG);
		return $this;
	}
}
?>