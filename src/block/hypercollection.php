<?
namespace core\block;
use core\core;

class hypercollection extends collection
{
	public const ROUTE_SESSION = 1;			// routes flags
	public const ROUTE_LAYOUT_CACHE = 2;
	public const ROUTE_CONTENT_CACHE = 4;
	public const ROUTE_NAVBAR = 8;

	protected $_route_flag = -1;
	protected $_user_flag = -1;
	protected $_route_id = 0;
	protected $_frontname = '';
	protected $_action = '';
	protected $_layout_data = null;
	protected $_default_bypass = false;		// исключить из результатов блоки, полученные от default frontname
	protected $_default_exists = false;		// присутствуют блоки, полученные от default frontname

	public function _construct($arg = null)
	{
		$this->_frontname = $this->getRequestFrontname();
		$this->_action = $this->getRequestAction();
		$this->_layout_data = core::get();
		parent::_construct();
	}
	public function altFrontName($frontname = 'index', $action = 'index')
	{
		$this->_frontname = $frontname;
		$this->_action = $action;
		return $this;
	}
	protected function _makeSelectFields()
	{
		return 'lt.`ref` AS ref,
				lt.`sort` AS sort,
				lt.`id` AS id,
				lt.`route` AS route,
				bl.`id` AS block,
				bl.`template` AS template,
				bl.`class` AS class,
				rt.`id` AS route_id,
				rt.`user_flag` AS user_flag,
				rt.`route_flag` AS route_flag,
				rt.`frontname` AS route_frontname,
				if(rt.`frontname` = "'.$this->_frontname.'",1,0) AS default_bypass,
				if(rt.`frontname` = "default",1,0) AS default_route';
	}
	protected function _makeSelectTables()
	{
		$this->addWhere('!rt.`locked`')
			->addWhere('!lt.`locked`')
			->addWhere('!bl.`locked`')
			->addWhere('(ISNULL(rt.`frontname`) 
				OR rt.`frontname` = "'.$this->_frontname.'"
				OR rt.`frontname` = "default")')
			->addWhere('rt.`action` = "'.$this->_action.'"')
			->addOrderby('lt.`ref` ASC')
			->addOrderby('lt.`sort` ASC')
			->addOrderby('lt.`id` ASC');
		return 'route AS rt
			INNER JOIN layout AS lt
				ON rt.`id` = lt.`route`
			INNER JOIN blocks AS bl
				ON bl.`id` = lt.`block_id`';
	}
	protected function _readData($result, $limit = NULL, $skip = 0)
	{
		$this->_default_bypass = false;
		$this->_default_exists = false;
		if($this->_num_rows = $result->num_rows)
		{
			$this->_setCreationMode(true);
			$offset = 0;
			$this->_collection = [];
			while($data = $result->fetch_object(static::$_model_class, [false, $this->_layout_data]))
			{
				if(!array_key_exists($data->getRef(),$this->_collection))
					$this->_collection[$data->getRef()] = [];
				if(!is_null($new_class = $data->getClass()))
				{
					$result->data_seek($offset);
					$data = $result->fetch_object($new_class, [false, $this->_layout_data]);
				}
				if($data->getDefaultBypass() && !$this->_default_bypass)
					$this->_default_bypass = true;
				if($data->getDefaultRoute() && !$this->_default_exists)
					$this->_default_exists = true;
				$this->_collection[$data->getRef()][] = $data;
				if(!$data->getDefaultRoute())	// флаги default rote не учитываются
				{
					$this->_user_flag &= $data->getUserFlag();
					$this->_route_flag &= $data->getRouteFlag();
				}
				if($data->getDefaultBypass())	// route_id учитывается только по "натуральному" route
					$this->_route_id = $data->getRoute();
				$offset++;
			}
			$this->_setCreationMode(false);
			$this->_status_loaded = true;
		}
		return $this;
	}
	public function getContentCacheAllowed()
	{
		return ($this->_route_flag & self::ROUTE_CONTENT_CACHE) ? true : false;
	}
	public function getLayoutCacheAllowed()
	{
		return ($this->_route_flag & self::ROUTE_LAYOUT_CACHE) ? true : false;
	}
	public function getRouteSession()
	{
		return ($this->_route_flag & self::ROUTE_SESSION) ? true : false;
	}
	public function getRouteUserFlag()
	{
		return intval($this->_user_flag);
	}
	public function getRouteFlag()
	{
		return intval($this->_route_flag);
	}
	public function getRouteId()
	{
		return $this->_route_id;
	}
	public function getBlocks($ref)
	{
		if(array_key_exists($ref,$this->_collection))					// такой reference существует
		{
			if($this->_default_exists && $this->_default_bypass)		// frontname найден. Удалить default route
				return array_filter($this->_collection[$ref], function($data) {
					return !$data->getDefaultRoute();
				});
			else if(!$this->_default_exists && !$this->_default_bypass)	// frontname не найден. default route не задан
				return null;
			else														// либо найден frontname, либо есть default route
				return $this->_collection[$ref];
		}
		return null;
	}
	public function getAllBlocks()
	{
		$layout = [];
		foreach(array_keys($this->_collection) as $ref)
		{
			if($data = $this->getBlocks($ref))
				$layout[$ref] = $data;
		}
		return $layout;			
	}
}
?>