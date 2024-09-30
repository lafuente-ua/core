<?
namespace core;
/** Collection base class */
class collection extends \core\core
{
	protected $_collection = [];
	protected $_wheres     = [];
	protected $_groupsby   = [];
	protected $_havings    = [];
	protected $_orders     = [];
	protected $_suffixes   = [];
	protected $_select     = '';
	protected $_stmt	   = null;

	protected static $_sql   = null;
	protected static $_redis = null;

	protected static $_model_class    = __NAMESPACE__.'\model';
	protected static $_model_table    = null;
	protected static $_auto_traverse  = false;	// always convert generators to arrays
	protected static $_cache_traverse = false;	// allows traverse generators for collection cache

	protected $_redis_key      = null;			// setCacheKey(...) for enable collection caching
	protected $_status_loaded  = false;			// data loaded, maybe empty
	protected $_status_cache   = false;			// cache have found and read
	protected $_num_rows	   = 0;

	public function _construct($arg = null)
	{
		if(!$this->_constructor_flag)
		{
			if(!self::$_sql)
			{
				self::$_sql   = db::get()->sql();
				self::$_redis = db::get()->redis();
			}
			parent::_construct($arg);
		}
	}
	protected function _makeSelectFields()
	{
		return '*';
	}
	protected function _makeSelectTables()
	{
		return static::$_model_table;
	}
	protected function _makeSelect($type)
	{
		switch($type)
		{
			case('delete'):
				$this->_makeSelectTables();	// Если нужно что-то сделать в гиперколлекции перед delete
				$select_where	= count($this->_wheres) ? (' where '.implode(' and ',$this->_wheres)) : '';
				$this->_select = 'delete from '. static::$_model_table . $select_where;
				break;
			case('select'):
				$select_fields	= $this->_makeSelectFields();
				$select_tables	= $this->_makeSelectTables();
				$select_where	= count($this->_wheres)		? (' where '.implode(' and ',$this->_wheres)) : '';
				$select_group	= count($this->_groupsby)	? (' group by '.implode(', ',$this->_groupsby)) : '';
				$select_having	= count($this->_havings)	? (' having '.implode(' and ',$this->_havings)) : '';
				$select_order	= count($this->_orders)		? (' order by '.implode(', ',$this->_orders)) : '';
				$select_suffix	= count($this->_suffixes)	? (' '.implode(' ',$this->_suffixes)) : '';
				$this->_select = 'select ' . $select_fields . ' from '. $select_tables . $select_where . $select_group . $select_having . $select_order . $select_suffix;
				break;
			default:
				throw new \Exception('Unknown collection request for '.static::class);
		}
		return $this;
	}
	public function setCacheKey($key)
	{
		$this->_redis_key = $key;
		return $this;
	}
	public function count()
	{
		if($this->_status_loaded)
			return $this->_num_rows;
		else
		{
			$this->_makeSelect('select');
			$result = self::$_sql->query($this->_select);
			if(self::$_sql->errno)
				throw new \Exception("MySQL error:\n ".self::$_sql->error." \n In ".static::class);
			return $result->num_rows;
		}
	}
	public function prepare()
	{
		$this->_status_loaded = false;
		$this->_num_rows = 0;
		$this->dispatchEvent('prepare',$this);
		$this->_makeSelect('select');
		$this->_stmt = self::$_sql->prepare($this->_select);
		if(self::$_sql->errno)
			throw new \Exception("MySQL STMT error:\n ".self::$_sql->error." \n In ".static::class);
		$this->dispatchEvent('prepared',$this);
		return $this;
	}
	public function bind($param)
	{
		if($this->_stmt instanceof \mysqli_stmt)
		{
			$this->_status_loaded = false;
			$this->_num_rows = 0;
			$this->dispatchEvent('bind',$this);
			call_user_func_array([$this->_stmt,'bind_param'], $param);
			if($this->_stmt->errno)
				throw new \Exception("MySQL STMT error:\n ".$this->_stmt->error." \n In ".static::class);
			$this->dispatchEvent('binded',$this);
		} 
		else
			throw new \Exception("Unprepared query binded in ".static::class);
		return $this;
	}
	public function execute($read_data = true, $limit = null, $skip = 0)
	{
		if($this->_stmt instanceof \mysqli_stmt)
		{
			$this->_status_loaded = false;
			$this->_num_rows = 0;
			$this->dispatchEvent('execute',$this);
			$this->_stmt->execute();
			if($this->_stmt->errno)
				throw new \Exception("MySQL STMT error:\n ".$this->_stmt->error." \n In ".static::class);
			if($read_data)
				$this->_readData($this->_stmt->get_result(), $limit, $skip);
			$this->dispatchEvent('executed',$this);
		}
		else
			throw new \Exception("Unprepared query execute in ".static::class);
		return $this;
	}
	public function load($limit = null, $skip = 0)
	{
		$this->_stmt = null;
		$this->_status_loaded = false;
		$this->_num_rows = 0;
		$this->dispatchEvent('load',$this);
		if($this->_redis_key
			&& self::$_redis
			&& (self::$_redis->exists(db::REDIS_COLLECTION_PREFIX.static::class.'_'.$this->_redis_key)))
		{
			$this->_collection = $this->_readCachedData();
			$this->_status_loaded = true;
		}
		else
		{
			$this->_makeSelect('select');
			$result = self::$_sql->query($this->_select);
			if(self::$_sql->errno)
				throw new \Exception("MySQL error:\n ".self::$_sql->error." \n In ".static::class);
			$this->_readData($result, $limit, $skip);
		}
		$this->dispatchEvent('loaded',$this);
		return $this;
	}
	public function delete($limit = null, $skip = 0)
	{
		$this->dispatchEvent('delete',$this);
		$this->_makeSelect('delete');
		self::$_sql->query($this->_select);
		$this->unload();
		$this->dispatchEvent('deleted',$this);
		return $this;
	}
	public function unCache()
	{
		//echo "\nClean";
		self::$_redis?->unlink(self::$_redis?->getKeys(db::REDIS_COLLECTION_PREFIX.static::class.'_*'));
		return $this;
	}
	protected function _cacheData($result)
	{
		if(is_array($result))
		{
			self::$_redis->setEx(db::REDIS_COLLECTION_PREFIX.static::class.'_'.$this->_redis_key,
				db::REDIS_COLLECTION_TTL, serialize($result));
			//echo "\nRedis collection saved ".db::REDIS_COLLECTION_PREFIX.static::class.'_'.$this->_redis_key;
		}
		return $this;
	}
	protected function _readCachedData()
	{
		$this->_status_cache = true;
		if(($data = unserialize(self::$_redis->get(db::REDIS_COLLECTION_PREFIX.static::class.'_'.$this->_redis_key)))
			&& is_array($data))
		{
			//echo "\nRedis collection loaded ".$this->_redis_key;
			return $data;
		}
		else
			return [];
	}
	protected function _readData($result, $limit = null, $skip = 0)
	{
		if($this->_num_rows = $result->num_rows)
			$this->_collection = $this->_readDataGenerator($result, $limit, $skip);
		else
			$this->_collection = [];
		$this->_status_loaded = true;
		return $this;
	}
	protected function _readDataGenerator($result, $limit = null, $skip = 0)
	{
		$id = 1;
		$this->_setCreationMode(true);
		//echo "\nRead collection real data";
		while((is_null($limit) || ($id <= ($skip + $limit))) 
			&& ($data = $result->fetch_object(static::$_model_class)))
		{
			if($id++ > $skip)
			{
				$this->_setCreationMode(false);
				yield $data;
				$this->_setCreationMode(true);
			}
		}	
		$this->_setCreationMode(false);
	}	
	public function getAllItemsArray($limit = null, $skip = 0)
	{
		if(($result = $this->_getAllItems($limit, $skip)) instanceof \Generator)
		{
			//echo "\nTraversing data";
			$result = iterator_to_array($result,false);
		}
		if($this->_redis_key &&	!$this->_status_cache)
		{
			$this->_cacheData($result);
			$this->_redis_key = null;
		}
		$this->_status_cache = false;
		return $result;
	}
	public function getAllItems($limit = null, $skip = 0)
	{
		return (static::$_auto_traverse 
			|| (static::$_cache_traverse && $this->_redis_key)) ? 
			$this->getAllItemsArray($limit, $skip) :
			$this->_getAllItems($limit, $skip);
	}
	protected function _getAllItems($limit = null, $skip = 0)
	{
		if(!$this->_status_loaded)
			$this->load($limit,$skip);
		return $this->_collection;
	}
	public function getFirstItem($skip = 0)
	{
		if(!$this->_status_loaded)
			$this->load(1,$skip);
		if($this->_num_rows)
			return $this->_collection->current();
		else
			return null;
	}
	public function addWhere($field, $value = null, $cond = null)
	{
		$this->_wheres[] = is_null($value) ? $field : ('`'.$field.'` '.($cond ?: '=').' "'.$value.'"');
		return $this;
	}
	public function addHaving($field, $value = null, $cond = null)
	{
		$this->_havings[] = is_null($value) ? $field : ('`'.$field.'` '.($cond ?: '=').' "'.$value.'"');
		return $this;
	}
	public function addOrder($field,  $cond = null)
	{
		$this->_orders[] = '`'.$field.'` '.($cond ?: 'asc');
		return $this;
	}
	public function addGroupBy($field)
	{
		$this->_groupsby[] = $field;
		return $this;
	}
	public function addOrderBy($field)
	{
		$this->_orders[] = $field;
		return $this;
	}
	public function addOrderSuffix($field)
	{
		$this->_suffixes[] = $field;
		return $this;
	}
	public function getSqlError()
	{
		return self::$_sql->error;
	}
	public function save($insert = false)
	{
		throw new \Exception('Access denied. Impossible to save collection for '.static::class);
	}
	public function getSelect()
	{
		return $this->_select;
	}
	public function wipeSelect()
	{
		$this->_wheres        = [];
		$this->_orders        = [];
		$this->_groupsby      = [];
		$this->_havings       = [];
		$this->_suffixes      = [];
		$this->_select        = '';
		$this->_stmt		  = null;
		return $this;
	}
	public function unload()
	{
		$this->dispatchEvent('unload',$this);
		$this->_collection    = [];
		$this->wipeSelect();
		$this->_num_rows      = 0;
		$this->_redis_key     = false;
		$this->_status_loaded = false;
		$this->_status_cache  = false;
		$this->dispatchEvent('unloaded',$this);
		return $this;
	}
	/** Return class name */
	public function who()
	{
		return static::class;
	}
	public function getById($id)
	{
		return $this->unload()
			->addWhere('id',$id)
			->load()
			->getFirstItem();
	}
	public function toObjects()
	{
		if($this->_collection)
		{
			$data = [];
			foreach($this->_collection as $dta)
				$data[] = $dta->toObject();
			return $data;
		}
		return null;
	}
	public function __toString()
	{
		return static::class;
	}
}
/* Sample:
\my\collection::get()
	->addWhere('`name` = ?')
	->prepare()
	->bind(['s',&$nm])
	->execute()
	->getFirstItem()
	->getName();
*/
?>