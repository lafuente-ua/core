<?
namespace core;
use core\app;
use core\event;
use core\config;
use core\translate;

/** Base class of models & collections*/
class core
{
	private const SINGLETON_STRICT = false;		// deny instance after singleton
	protected const TEXT_STRICT = true;			// autotrim text data

	protected static $_creation_mode = false;
	private static $_singleton_mode = [];		// modes of models (singleton or no)
	private static $_singletones = [];			// all created singletons
	private static $_global_core_data = [];		// app-wide data
	private static $_translator = null;
	private static $_mtranslator = null;
	
	protected $_constructor_flag = false;		// constructor complete
	protected $_core_data = [];
	protected $_json_data = [];
	protected $_core_injection = null;

	protected static $_observers_allowed = false;		// allowed to redefine
	protected static $_autosingleton	 = false;

	public final function __construct($singleton = false, $arg = null)
	{
		if(!self::$_creation_mode)
			throw new \Exception('Can\'t create CoreMVC instance "'.static::class.'" by "new" operator.');
		else
		{
			if(static::isSingleton())
				throw new \Exception('Can\'t create CoreMVC instance "'.static::class.'". Singleton already exists.');
			else
			{
				self::$_singleton_mode[static::class] = $singleton ?: static::$_autosingleton;
				$this->_construct($arg);
			}
		}
	}
	public final function __destruct()
	{
		$this->_destruct();
		if(array_key_exists(static::class,self::$_singletones))
			unset(self::$_singletones[static::class]);
		if(array_key_exists(static::class,self::$_singleton_mode))
			unset(self::$_singleton_mode[static::class]);
	}
	/** Internal constructor with data injection*/
	public function _construct($arg = null)
	{
		$this->_core_injection = $arg;
		$this->_constructor_flag = true;
	}
	/** Internal destructor*/
	public function _destruct()
	{
	}
	protected final function _getCreationMode()
	{
		return self::$_creation_mode;
	}
	/** Allows call constructor*/
	protected final function _setCreationMode($value = false)
	{
		self::$_creation_mode = ($value ? true : false);
	}
	public static final function isSingleton()
	{
		return (array_key_exists(static::class,self::$_singleton_mode) 
			&& (self::$_singleton_mode[static::class] === true)) ? true : false;
	}
	public static final function isInstance()
	{
		return (array_key_exists(static::class,self::$_singleton_mode) 
			&& (self::$_singleton_mode[static::class] === false)) ? true : false;
	}
	/** Get object of the class*/
	public static function get($arg = null)
	{
		if(static::isSingleton())
		{
			if(self::SINGLETON_STRICT)
				throw new \Exception('Can\'t create CoreMVC instance "'.static::class.'". Singleton already exists.');
			else
			{
				if(array_key_exists(static::class,self::$_singletones))
					return self::$_singletones[static::class];
				else
					throw new \Exception('Can\'t create CoreMVC instance "'.static::class.'". Dropped singleton exists.');
			}
		}
		self::$_creation_mode = true;
		$instance = new static(false, $arg);
		self::$_creation_mode = false;
		if(static::$_autosingleton)
			self::$_singletones[static::class] = $instance;
		return $instance;
	}
	public static function getSingleton($arg = null)
	{
		if(!static::isInstance())
		{
			if(!array_key_exists(static::class,self::$_singletones))
			{
				self::$_creation_mode = true;
				self::$_singletones[static::class] = new static(true,$arg);
				self::$_creation_mode = false;
			}
			return self::$_singletones[static::class];
		}
		else
			throw new \Exception('Can\'t create singleton. "'.static::class.'". Instances already exists.');
		return null;
	}
	public function toObject($nulls = true)
	{
		$result = new \stdClass();
		foreach($this->_core_data as $name => $value)
		{
			if(!is_null($value) || $nulls)
				$result->$name = $value;
		}
		return $result;
	}
// Magic methods

	public function __set($name, $value)
	{
		throw new \Exception('Access denied. Use setter for "'.static::class.'::'.$name.'"');
	}
    public final function __get($name) 
    {
		throw new \Exception('Access denied. Use getter for "'.static::class.'::'.$name.'"');
    }
    public final function __isset($name) 
    {
        throw new \Exception('Access denied. Use isSetter for "'.static::class.'::'.$name.'"');
    }
    public final function __unset($name) 
	{
        throw new \Exception('Access denied. Use unsetter for "'.static::class.'::'.$name.'"');
	}
	public final function __wakeup()
	{
		if(static::isSingleton())
			throw new \Exception("Can't unserialize singleton.");
	}
	public final function __clone()
	{
		if(static::isSingleton())
			throw new \Exception("Can't clone singleton.");
	}
	public static function __callStatic($name, $arguments)
	{
		throw new \Exception('Unknown static method "'.static::class.'::'.$name.'"');
	}
	public function __call($name, $arguments)
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
				case 'set':
					if(is_array($arguments) 
						&& array_key_exists(0,$arguments))
					{
						$this->_setPropertyByName($var_name, $arguments[0]);
					}
					break;
				case 'unset':
					$this->_unsetPropertyByName($var_name);
					break;
				case 'gset':
					if(is_array($arguments)
						&& array_key_exists(0,$arguments))
						$this->_setGlobalCoreData($var_name,$arguments[0]);
					break;
				case 'gunset':
					$this->_unsetGlobalCoreData($var_name);
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

    protected function _getPropertyByName($name)
    {
		return array_key_exists($name, $this->_core_data) ? 
			$this->_core_data[$name] : $this->_getGlobalCoreData($name);
    }	
	protected function _setPropertyByName($name, $value) 
	{
		$this->_core_data[$name] = $value;
		return $this;
	}
	protected function _unsetPropertyByName($name) 
	{
		if(array_key_exists($name, $this->_core_data))
			unset($this->_core_data[$name]);
		return $this;
	}
	protected final function _getGlobalCoreData($name)
	{
		if(array_key_exists($name, self::$_global_core_data))
			return self::$_global_core_data[$name];
		return null;
	}
	protected final function _getAllGlobalCoreData()
	{
		return self::$_global_core_data;
	}
	protected final function _setGlobalCoreData($name,$value)
	{
		self::$_global_core_data[$name] = $value;
		return $this;
	}
	protected final function _unsetGlobalCoreData($name)
	{
		if(array_key_exists($name, self::$_global_core_data))
			unset(self::$_global_core_data[$name]);
		return $this;
	}
	/** Return config value by name*/
	public function config($name)
	{
		return config\collection::get()->getByName($name);
	}
	/** Store config value by name*/
	public function configStore($name, $value)
	{
		return config\collection::get()->storeByName($name, $value);
	}

// Events managing

	public function dispatchEvent($event, $data = null)
	{
		if(static::$_observers_allowed)
		{
			$events = event\hypercollection::get();
			if(is_null($data))
				$data = $this;
			foreach(($observers = $events
				->addWhere('evn.`name`="'.$event.'"')
				->addWhere('evn.`class`="'.addslashes(static::class).'"')
				->load()
				->getAllItems()) as $obs)
			{
				$observer = ($obs->getObserver())::get();
				if($observer instanceof \core\observer)
				{
					if(method_exists($observer, $method = $obs->getMethod()))
					{
						if(!$observer->$method($data))
							break;
					}
					else
						throw new \Exception('Invalid observer method '.$obs->getObserver().'::'.$method.' of "'.static::class.'"');
				}
				else
					throw new \Exception('Invalid observer '.$obs->getObserver().' of "'.static::class.'"');
			}
		}
	}
	//=== APP access
	public function getArgs($name = null)
	{
		return app::getRequestData('args', $name);
	}
	public function getPost($name = null)
	{
		return app::getRequestData('post', $name);
	}
	public function getRequest($name = null)
	{
		return app::getRequestData('request', $name);
	}
	public function getRequestFrontname()
	{
		return app::getRequestData('frontname');
	}
	public function getRequestAction()
	{
		return app::getRequestData('action');
	}
	/** Translate text to specific language*/
	public function _t($text, $lang = null, $replace = [])	
	{
		if(!self::$_translator)	// Specific will be main
		{
			$this->setCurrentLanguage($lang);
			self::$_translator = translate\hypercollection::get();
			$text = self::$_translator->getTranslated($text, $replace);
		}
		else if($lang)		// Temporary lang
		{
			$last = $this->getCurrentLanguage();
			$this->setCurrentLanguage($lang);
			$text = self::$_translator->getTranslated($text, $replace);
			$this->setCurrentLanguage($last);
		}
		else				// Simple current lang
			$text = self::$_translator->getTranslated($text, $replace);
		return $text;
	}	
	/** Translate text to current language*/
	public function _($text, $replace = [])	
	// echo _('Hello @qty @Users and @slaves',['qty' => 25, 'users' => 'admins', 'slaves' => 'subs']);
	// Привіт 25 Адмінів та учнів
	{
		if(!self::$_translator)
		{
			if(!$this->getCurrentLanguage())
			{
				$this->setCurrentLanguage();
			}
			self::$_translator = translate\hypercollection::get();
		}
		return self::$_translator->getTranslated($text, $replace);
	}	
	/** Translate text to all languages*/
	public function tt($text)	// translate text multi
	{
		if(!self::$_mtranslator)
			self::$_translator = translate\multicollection::get();
		return self::$_mtranslator->getTranslated($text);
	}
	public function setCurrentLanguage($lang = null)
	{
		if(!$lang)
			$lang = translate\model::LANG_DEFAULT; 
		if(app::getSessionAllowed())
		{
			$this->setSession('language',$lang);
		}
		$this->gsetCurrentAppLanguage($lang);
		return $this;
	}
	public function getCurrentLanguage()
	{
		return app::getSessionAllowed() ? 
			$this->getSession('language') : $this->getCurrentAppLanguage();
	}
	public static function getSession($name)
	{
		if(app::getSessionAllowed())
		{
			if(array_key_exists($name, $_SESSION))
				return $_SESSION[$name];
		}
		return null;
	}
	public static function setSession($name, $value)
	{
		if(app::getSessionAllowed())
			$_SESSION[$name] = $value;
	}
	public static function unsetSession($name)
	{
		if(app::getSessionAllowed())
			unset($_SESSION[$name]);
	}
	/**Get environment object */
	public static function env()
	{
		return app::env();
	}
	/**Return formatted url from existing url and alernate data: {args, lang, frontname, action, merge}*/
	public function getUrl($url = null, $data = new \stdClass())
	{
		if(is_null($url))
		{
			$url_data = app::getRequestData(null);
		}
		else
		{
			$url_data = app::parseUri($url);
			$url_data->lang = $this->getCurrentLanguage();
		}
		return $this->_createUrl($url_data, $data);
	}
	protected function _createUrl($request, $data = new \stdClass())
	{
		// data: { args,merge,lang,frontname,action }

		$frontname = isset($data->frontname) ? $data->frontname : $request->frontname;
		$lang = isset($data->lang) ? $data->lang : $this->getCurrentLanguage();
		$action = isset($data->action) ? $data->action : $request->action;
		$args = isset($data->args) ? $data->args : null;
		if(isset($data->merge))
		{
			if(is_array($args))
				$total_args = array_merge($request->args, $args);
			else
				$total_args = $request->args;
		}
		else
			$total_args = is_array($args) ? $args : null;
		if(is_array($total_args))
		{
			foreach($total_args as $id => $targ)
			{
				if(is_null($targ))
					unset($total_args[$id]);
			}
		}
		$url = ($lang == translate\model::LANG_DEFAULT) ?
			'' : DIRECTORY_SEPARATOR.$lang;
		$url .= DIRECTORY_SEPARATOR.$frontname;
		if($action != 'index')
			$url .= DIRECTORY_SEPARATOR . $action;
		if(is_array($total_args))
		{
			foreach($total_args as $id => $val)
			{
				if(!is_null($val))
					$url .= DIRECTORY_SEPARATOR . $id. DIRECTORY_SEPARATOR . $val;
			}
		}
		return $url;
	}
}
?>