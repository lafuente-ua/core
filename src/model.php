<?
namespace core;
/** Model base class
*/
class model extends \core\core
{
	const FIELDTYPE_BIT = 1;
	const FIELDTYPE_INT = 2;
	const FIELDTYPE_FLOAT = 3;
	const FIELDTYPE_QUOTED = 10;	// >= need quotes
	const FIELDTYPE_CHAR = 11;
	const FIELDTYPE_DATETIME = 12;

	protected static $_sql = null;
	protected static $_redis = null;
	private static $_tables = [];

	protected static $_table_name = null;
	protected static $_json_data_allowed = false;

	protected $_select = null;
	protected $_status_loaded = false;
	protected $_status_saved  = false;

	public function _construct($arg = null)
	{
		if(!$this->_constructor_flag)
		{
			if(!self::$_sql)
			{
				self::$_sql   = db::get()->sql();
				self::$_redis = db::get()->redis();
			}
			$this->_maping();
		}
		parent::_construct($arg);
	}
	/** Table structuriser */
	private function _maping()
	{
		if(static::$_table_name)
		{
			if(!array_key_exists(static::$_table_name, self::$_tables))	// look exists
			{
				if(self::$_redis?->exists($redis_key = db::REDIS_TABLE_PREFIX.static::$_table_name))
				{
					//echo "\nRead redis model ".$redis_key;
					self::$_tables[static::$_table_name] = unserialize(self::$_redis->get($redis_key));
					$this->_jsonDataInit();
				}
				else
				{
					$table	= (object)[
						'columns' => [],
						'primary' => false,
						'jsones' => []];
					$sqlr = self::$_sql->query('show columns from `'.static::$_table_name.'`');
					if($sqlr->num_rows)
					{
						while($column = $sqlr->fetch_object())
						{
							$type = self::FIELDTYPE_CHAR;
							$origin = $column;
							$format = null;
							$json = null;
							if(!(strpos($column->Type,'int') === FALSE))
								$type = self::FIELDTYPE_INT;
							if(!(strpos($column->Type,'bit') === FALSE))
								$type = self::FIELDTYPE_BIT;
							else if(!(strpos($column->Type,'year') === FALSE))
								$type = self::FIELDTYPE_INT;
							else if(!(strpos($column->Type,'float') === FALSE))
								$type = self::FIELDTYPE_FLOAT;
							else if(!(strpos($column->Type,'double') === FALSE))
								$type = self::FIELDTYPE_FLOAT;
							else if(!(strpos($column->Type,'decimal') === FALSE))
								$type = self::FIELDTYPE_FLOAT;
							else if(!(strpos($column->Type,'datetime') === FALSE))
								$type = self::FIELDTYPE_DATETIME;
							else if(!(strpos($column->Type,'timestamp') === FALSE))
							{
								$type = self::FIELDTYPE_DATETIME;
								$format = \core\time::FORMAT_DEFAULT;
							}
							else if(!(strpos($column->Type,'date') === FALSE))
							{
								$type = self::FIELDTYPE_DATETIME;
								$format = \core\time::FORMAT_DATE;
							}
							else if(!(strpos($column->Type,'time') === FALSE))
							{
								$type = self::FIELDTYPE_DATETIME;
								$format = \core\time::FORMAT_TIME;
							}
							if($column->Key == 'PRI')
								$table->primary = $column->Field;
							if(static::$_json_data_allowed &&
								!strncmp($column->Field,'json',4) && 
								!strcasecmp($column->Type,'text'))
							{
								$json = true;
								$table->jsones[] = $column->Field;
								$this->_json_data[$column->Field] = [];
							}
							$table->columns[$column->Field] = (object)['type' => $type, 'format' => $format, 'json' => $json, 'origin' => $origin];
						}
						self::$_tables[static::$_table_name] = $table;
					}
					else
						throw new \Exception('Table '.static::$_table_name.' not exists.');	
					if(self::$_redis)
					{
						self::$_redis->setEx(db::REDIS_TABLE_PREFIX.static::$_table_name,
							db::REDIS_TABLE_TTL, serialize(self::$_tables[static::$_table_name]));
						//echo "\nWrite redis model ".static::$_table_name;
					}
				}
			}
			else
				$this->_jsonDataInit();
			//print_r(self::$_tables);
		}
		return $this;
	}
	/** Init model json data by global tables data if json allowed */
	protected function _jsonDataInit()
	{
		if(static::$_json_data_allowed)
		{
			foreach(self::$_tables[static::$_table_name]->jsones as $jsn)
				$this->_json_data[$jsn] = [];
		}
		return $this;
	}
	public function isLoaded()
	{
		return $this->_status_loaded;
	}
	public function isSaved()
	{
		return $this->_status_saved;
	}
	public function load($id = null)
	{
		$this->_status_loaded = false;
		$this->_core_data = [];
		$this->_json_data = [];
		$this->dispatchEvent('load');
		if(static::$_table_name)
		{
			if(!is_null($id))
				$this->_setPrimary($id);
			if($this->_getPrimary())
			{
				$this->_makeSelect('select');
				$result = self::$_sql->query($this->_select);
				if(self::$_sql->errno)
					throw new \Exception('MySQL error '.self::$_sql->error." \n In ".static::class);
				$this->_readData($result);
			}
		}
		$this->dispatchEvent('loaded');
		return $this;
	}
	protected function _setPrimary($id)
	{
		if($name = self::$_tables[static::$_table_name]->primary)
			$this->_setPropertyByName($name, $id);
		return $this;
	}
	protected function _unsetPrimary()
	{
		if($name = self::$_tables[static::$_table_name]->primary)
			$this->_unsetPropertyByName($name);
		return $this;
	}
	protected function _getPrimary()
	{
		if($name = self::$_tables[static::$_table_name]->primary)
			return $this->_getPropertyByName($name);
		return null;
	}
	protected function _makeSelect($type)
	{
		$this->_select = null;
		if(static::$_table_name && self::$_tables[static::$_table_name])
		{
			switch($type)
			{
				case 'update':
					if($id = $this->_getPrimary())
					{
						$fields = [];
						foreach(self::$_tables[static::$_table_name]->columns as $field => $field_data)
						{
							if($field != self::$_tables[static::$_table_name]->primary)
							{
								if(!is_null($val = $this->_getNativePropertyByName($field))
									|| array_key_exists($field, $this->_core_data))
								{
									$fields[] = '`'.$field.'`='. 
										(is_null($val) ? 'null' : ( ($field_data->type < self::FIELDTYPE_QUOTED) ?
										$val : ('"'.addslashes($val).'"')));
								}
							}
						}
						$this->_select = count($fields) ? ('update '.static::$_table_name.' set '.implode(',',$fields).' where `'.self::$_tables[static::$_table_name]->primary.'`='.$id) : false;
					}
					break;
				case 'insert':
					$fields = [];
					$values = [];
					foreach(self::$_tables[static::$_table_name]->columns as $field => $field_data)
					{
						if(!is_null($val = $this->_getNativePropertyByName($field))
							|| array_key_exists($field, $this->_core_data))
						{
							$fields[] = '`'.$field.'`';
							$values[] = is_null($val) ? 'null' : ( ($field_data->type < self::FIELDTYPE_QUOTED) ?
								$val : ('"'.addslashes($val).'"'));
						}
					}
					$this->_select = 'insert into '.static::$_table_name;
					if(count($fields))
						$this->_select .= ' ('.implode(',',$fields).') values ('.implode(',',$values).')';
					break;
				case 'select':
					if($id = $this->_getPrimary())
						$this->_select = 'select * from `'.static::$_table_name.'` where `'.self::$_tables[static::$_table_name]->primary.'`='.$id;
					break;
				case 'delete':
					if($id = $this->_getPrimary())
						$this->_select = 'delete from `'.static::$_table_name.'` where `'.self::$_tables[static::$_table_name]->primary.'`='.$id;
					break;
			}
		}
		else
		{
			throw new \Exception("SQL not allowed withoun resources.");
		}
		//echo "\n".$this->_select."\n";
		return $this;
	}
	protected function _readData($sql_result)
	{
		if($sql_result->num_rows == 1)
		{
			$data = $sql_result->fetch_object();
			$this->setData($data);
		}
		return $this;
	}
	public function setData($data)
	{
		if(static::$_table_name
			&& (array_key_exists(static::$_table_name, self::$_tables)))
		{
			foreach(array_keys(self::$_tables[static::$_table_name]->columns) as $field)
			{
				$this->_setPropertyByName($field, 
					$this->_bringValueToType($field, $data->$field));
			}
			$this->_status_loaded = true;
		}
		return $this;
	}
	protected function _bringValueToType($name, $value)
	{
		if($name && static::$_table_name)
		{
			if(!is_null($value) 
				&& array_key_exists(static::$_table_name, self::$_tables) 
				&& array_key_exists($name, self::$_tables[static::$_table_name]->columns)
				&& is_object($field = self::$_tables[static::$_table_name]->columns[$name]))
			{
				switch($field->type)
				{
				case self::FIELDTYPE_INT:
					$value = intval($value);
					break;
				case self::FIELDTYPE_FLOAT:
					$value = floatval($value);
					break;
				case self::FIELDTYPE_DATETIME:
					$value = new \core\time($value);
					if(!is_null($field->format))
						$value->setFormat($field->format);
					break;
				case self::FIELDTYPE_CHAR:
					if(static::TEXT_STRICT)
						$value = trim($value);
					break;
				}
			}
			return $value;
		}
		return null;
	}
	public function toObject($nulls = true)
	{
		$result = new \stdClass();
		foreach($this->_core_data as $name => $value)
		{
			if((!static::$_json_data_allowed || strncmp($name,'json',4)) 
				&& (!is_null($value) || $nulls))
			{
				if(array_key_exists(static::$_table_name,self::$_tables)
					&& array_key_exists($name,self::$_tables[static::$_table_name]->columns)
					&& ($field = self::$_tables[static::$_table_name]->columns[$name])
					&& ($field->type == self::FIELDTYPE_DATETIME)
					&& ($value instanceof core_time))
						$result->$name = $value->get();
				else
					$result->$name = $value;
			}
		}
		return $result;
	}
	public function save($insert = false, $id = null)
	{
		$this->_status_saved = false;
		$this->dispatchEvent('save');
		if(static::$_json_data_allowed)
			$this->_jsonDataRestore();
		if(!$insert && ($id = $this->_getPrimary()))
		{
			$this->_makeSelect('update');
			self::$_sql->query($this->_select);
			if(!self::$_sql->errno)
			{
				$this->load($id);
				$this->_status_saved = true;
			}
		}
		else
		{
			if(is_null($id))
				$this->_unsetPrimary();
			else
				$this->_setPrimary($id);		// Was $this->unsetPrimary($id); ??
			$this->_makeSelect('insert');
			self::$_sql->query($this->_select);
			if(!self::$_sql->errno && self::$_sql->affected_rows)
			{
				$this->load(self::$_sql->insert_id);
				$this->_status_saved = true;
			}
		}
		if($this->_status_saved)
			$this->dispatchEvent('saved');
		return $this;
	}
	protected function _getNativePropertyByName($name)			// в исходном формате date-time
    {
		if(!is_null($value = (array_key_exists($name, $this->_core_data) ?
			$this->_core_data[$name] : null)))
		{
			switch($type = self::$_tables[static::$_table_name]->columns[$name]->type)
			{
				case self::FIELDTYPE_DATETIME:
					if($value instanceof \core\time)
					{
						$data = clone $value;
						return $data->setFormat(\core\time::FORMAT_SQL);
					}
					break;
				case self::FIELDTYPE_CHAR:
					if(static::TEXT_STRICT)
						return trim($value);
					break;
				default:
					return $value;
			}
		}
		return null;
    }

	public final static function get($arg = null, $arg1 = null)			// if resources exists: ($id, $injection)
	{
		$id = static::$_table_name ? intval($arg) : null;
		$inject = static::$_table_name ? $arg1 : $arg;

		if(static::isSingleton())
		{
			if(self::SINGLETON_STRICT)
				throw new \Exception('Can\'t create CoreMVC instance "'.static::class.'". Singleton already exists.');
			else
			{
				if(array_key_exists(static::class,self::$_singletones))
				{
					if($id)
						self::$_singletones[static::class]->load($id);
					return self::$_singletones[static::class];
				}
				else
					throw new \Exception('Can\'t create CoreMVC instance "'.static::class.'". Dropped singleton exists.');
			}
		}
		self::$_creation_mode = true;
		$instance = new static(false, $inject);
		self::$_creation_mode = false;
		if(static::$_autosingleton)
			self::$_singletones[static::class] = $instance;
		if($instance && $id)
			$instance->load($id);
		return $instance;
	}
	public final static function getSingleton($arg = null, $arg1 = null)
	{
		if(!static::isInstance())
		{
			$id = static::$_table_name ? intval($arg) : null;
			$inject = static::$_table_name ? $arg1 : $arg;

			if(!array_key_exists(static::class,self::$_singletones))
			{
				self::$_creation_mode = true;
				self::$_singletones[static::class] = new static(true, $inject);
				self::$_creation_mode = false;
			}
			if(self::$_singletones[static::class] && $id)
				self::$_singletones[static::class]->load($id);
			return self::$_singletones[static::class];
		}
		else
			throw new \Exception('Can\'t create singleton. "'.static::class.'". Instances already exists.');
		return null;
	}

// Magic methods

	public final function __set($name, $value)
	{
		if($this->_getCreationMode())
		{
			if(static::$_autosingleton)
				throw new \Exception('Can\'t create singleton "'.static::class.'" in collection');
			if(!$this->_constructor_flag)
			{
				$this->_construct();
				$this->_status_loaded = true;
			}
			$this->_setPropertyByName($name, $this->_bringValueToType($name, $value));
		}
		else
			throw new \Exception('Access denied. Use setter for "'.static::class.'::'.$name.'"');
	}
	public final function __call($name, $arguments)
	{
		if((preg_match('/(^[a-z]+)(?=[A-Z])/', $name, $prefix)) &&
			($call_prefix = $prefix[0]) &&
			$var_name = substr(strtolower(preg_replace('/([A-Z]{1})/','_$1',$name)),1+strlen($call_prefix)))
		{
			switch($call_prefix)
			{
				case 'get':
					return $this->_getPropertyByName($var_name);
					break;
				case 'jset':
					if(static::$_json_data_allowed)
					{
						if(is_array($arguments) 
							&& array_key_exists(1,$arguments) 
							&& $arguments[1])
							$json_field = intval($arguments[1]);	// порядковый номер json-блока в модели
						else
							$json_field = 0;
						$jsons = array_keys($this->_json_data);		// имена json-блоков
						// echo ' JSET '.$json_field.' '.implode('-',$jsons);
						if(is_array($jsons) 
							&& (count($jsons) > $json_field) 
							&& (!array_key_exists($var_name, $this->_json_data[$jsons[$json_field]])))
						{
							// echo ' JSET NEW NAME '.$var_name.' ';
							$this->_json_data[$jsons[$json_field]][] = $var_name;
						}
					}
				case 'set':
					if(is_array($arguments) 
						&& array_key_exists(0,$arguments))
						$this->_setPropertyByName($var_name, $arguments[0]);
					break;
				case 'junset':
					foreach($this->_json_data as $jname => $jbody)
					{
						if(!(($key = array_search($var_name, $jbody)) === FALSE))
							array_splice($this->_json_data[$jname], $key, 1);
					}
				case 'unset':
					$this->_unsetPropertyByName($var_name);
					break;
				case 'gset':
					if(is_array($arguments) && array_key_exists(0,$arguments))
						$this->_setGlobalCoreData($var_name,$arguments[0]);
					break;
				case 'gunset':
					$this->_unsetGlobalCoreData($var_name);
					break;
				case 'rget':
					if(self::$_redis)
					{
						return (self::$_redis?->exists($redis_key = db::REDIS_DATA_PREFIX.$var_name)) ?
							self::$_redis?->get($redis_key) : null;
					}
					else
						throw new \Exception('Impossible rget without Redis');
					break;
				case 'rset':
					if(self::$_redis)
					{
						if(is_array($arguments) && array_key_exists(0,$arguments))
						{
							if(is_array($arguments[0]) || is_object($arguments[0]))
								throw new \Exception('Impossible rset object');
							else
							{
								self::$_redis?->setEx(db::REDIS_DATA_PREFIX.$var_name,
									db::REDIS_DATA_TTL, $arguments[0]);
							}
						}
					}
					else
						throw new \Exception('Impossible rset without Redis');
					break;
				default:
					throw new \Exception('Unknown method '.static::class.'::'.$name.'()');
					break;
			}
		}
		else
			throw new \Exception('Unknown method '.static::class.'::'.$name.'()');
		return $this;
	}

// Properties management

    protected final function _getPropertyByName($name)
    {
		if(static::$_json_data_allowed && !strncmp($name,'json',4))
			throw new \Exception('Don\'t use "json" as property name.');
		else
		{
			return array_key_exists($name, $this->_core_data) ? 
				$this->_core_data[$name] : $this->_getGlobalCoreData($name);
		}
    }	
	protected final function _setPropertyByName($name, $value) 
	{
		if(static::$_json_data_allowed && !strncmp($name,'json',4))
		{
			if(!array_key_exists($name, $this->_json_data))
				$this->_json_data[$name] = [];
			if($value)
			{
				$json = json_decode($value);
				if((json_last_error() == JSON_ERROR_NONE) && $json)
				{
					foreach($json as $nm => $val)
					{	
						if(!array_key_exists($nm, $this->_core_data))	// unical properties only
						{
							$this->_core_data[$nm] = $val;
							$this->_json_data[$name][] = $nm;
						}
					}
				}
			}
		}
		else
			$this->_core_data[$name] = $value;
		return $this;
	}
	protected final function _jsonDataRestore()
	{
		foreach($this->_json_data as $jname => $jidx)
		{
			if(count($jidx))
			{
				$json = new \stdClass();
				foreach($jidx as $idx)
				{
					$json->$idx = $this->_core_data[$idx];	
					// Warning: Undefined array key "token" if jset() for non-json property
				}
				$this->_core_data[$jname] = json_encode($json, JSON_UNESCAPED_UNICODE);
			}
			else
				$this->_core_data[$jname] = '';
		}
		return $this;
	}
	protected final function _unsetPropertyByName($name) 
	{
		if(static::$_json_data_allowed && !strncmp($name,'json',4))
			throw new \Exception('Don\'t use "json" as property name.');
		else if(array_key_exists($name, $this->_core_data))
			unset($this->_core_data[$name]);
		return $this;
	}
	public function sqlRollback()
	{
		self::$_sql->rollback();
		return $this;
	}
	public function sqlCommit()
	{
		self::$_sql->commit();
		return $this;
	}
	public function sqlTransaction()
	{
		self::$_sql->begin_transaction();
		return $this;
	}
}
?>