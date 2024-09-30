<?
namespace core\block;
use core\app;

class model extends \core\model
{
	protected static $_table_name = 'blocks';
	protected const TEMPLATE_PATH = './template/';
	protected const CSRF_LENGTH = 8;
	protected $_block_path = null;
	protected $_block_args = null;

	public function renderLayout($args = null)
	{
		if(!$this->getLocked() && !is_null($this->getTemplate()))
		{
			$this->_block_args = $args;
			if(!strncmp($this->getTemplate(),'_',1))								// redirect на новый ref
				$this->placeRef(substr($this->getTemplate(),1),$args);
			else if(file_exists($this->_block_path = static::TEMPLATE_PATH .		// template файл
				str_replace('_',DIRECTORY_SEPARATOR,$this->getTemplate()).'.php'))
				$this->_renderLayout();
			else
				throw new \Exception('Invalid template '.$this->getTemplate());
		}
		return $this;
	}
	protected function _renderLayout()
	{
		$this->setLayoutData($this->_core_injection);
		include($this->_block_path);
	}
	public function getArg()
	{
		return $this->_block_args();
	}
	public function placeRef($ref, $args = null)
	{
		app::placeRef($ref, $args);
		return $this;
	}
	public function csrf()
	{
		$this->setSession('csrf',\core\helper::keyGen(self::CSRF_LENGTH));
		return $this->getSession('csrf');
	}
	public function inputCsrf()
	{
		echo '<input type="hidden" name="csrf" value="'.$this->csrf().'"/>';
	}
}
?>