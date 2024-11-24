<?
namespace core;
use core\db;
use core\block;
/** Applicaton static class */
class app
{
	private const SESSION_TTL = 0;	// 345600;	// 4 days

	private static $_redis = null;
	private static $_layout = null;
	private static $_session_allowed = true;
	private static $_session_started = false;
	private static $_content_cache_allowed = true;
	private static $_layout_cache_allowed = true;
	private static $_request_data = null;
	private static $_bad_ref = [];
	private static $_session_ttl = self::SESSION_TTL;
	private static $_env = null;

	private static function _init()
	{
		self::_parseUri();
		self::_initPost();
		self::_initLayout();
		if(self::$_session_allowed)
			self::$_session_allowed = self::$_layout->getRouteSession();
		if(self::$_content_cache_allowed)
			self::$_content_cache_allowed = self::$_layout->getContentCacheAllowed();
		if(self::$_session_allowed)
			self::_initSession();
		self::$_layout->setCurrentLanguage(self::$_request_data->lang);
	}
	private static function _initPost()
	{
		if(array_key_exists('CONTENT_TYPE',$_SERVER) 
			&& (trim($_SERVER["CONTENT_TYPE"]) == "application/json"))
		{
			self::$_request_data->post = (array) json_decode($uu = trim(file_get_contents("php://input")));
			if(json_last_error() != JSON_ERROR_NONE)
				self::$_request_data->post = [];
		}
		else
			self::$_request_data->post = $_POST;
	}
	private static function _parseUri($request_uri = null)
	{
		self::$_request_data = self::parseUri($request_uri);
		self::$_request_data->post = [];
		self::$_request_data->request = null;
	}
	public static function parseUri($request_uri = null)		// Make universal
	{
		$lang = \core\translate\model::LANG_DEFAULT;
		$frontname = 'index';
		$action = 'index';
		$args = [];
		if(is_null($request_uri))
			$request_uri = $_SERVER['REQUEST_URI'];
		$request_uri = trim($request_uri, " #&?\n\r\t\v\x00\/");
		$uri_parts = $request_uri ? explode(DIRECTORY_SEPARATOR, $request_uri) : [];
		if((count($uri_parts) > 0) && 
			(strlen($uri_parts[0]) == 2) && 
			\core\translate\model::isPossible($uri_parts[0]))
			$lang = array_shift($uri_parts);
		if(($uris = count($uri_parts)) > 0)
			$frontname = $uri_parts[0];
		$arg_num = ($uris - 1) >> 1;
		$uris--;
		$idx = 0;
		while($uris > 0)
		{
			if($idx < $arg_num)
			{
				if(!is_numeric($uri_parts[$uris-1]))
					$args[$uri_parts[$uris-1]] = $uri_parts[$uris];
			}
			else
				$action = $uri_parts[$uris];
			$uris -= 2;
			$idx ++;
		}
		return (object)['lang' => $lang,
			'frontname' => $frontname,
			'action' => $action,
			'args' => (count($args)) ? array_reverse($args) : []];
	}
	private static function _initSession()
	{
		if(!self::$_session_started)
		{
			session_start([
			'name' => 'CORESSID',
			'use_only_cookies' => 1,
			'use_strict_mode' => 1,
			'save_handler' => 'files',
			'cookie_lifetime' => (self::$_session_ttl ? (time() + self::$_session_ttl) : 0),
			'cookie_httponly' => 1,
			'cookie_secure' => 1,
			'cookie_domain' => ''
			]);
			session_regenerate_id();
			self::$_session_started = true;
		}
	}
	private static function _initLayout()
	{
		if(self::$_layout_cache_allowed)
		{
			$layout = db::REDIS_LAYOUT_PREFIX.self::$_request_data->frontname.'_'.self::$_request_data->action;
			if(self::$_redis->exists($layout))
				self::$_layout = unserialize(self::$_redis->get($layout));
			else
			{
				self::$_layout = block\hypercollection::get()->load();
				if(self::$_layout->getLayoutCacheAllowed())
					self::$_redis->setEx($layout,db::REDIS_LAYOUT_TTL,
						serialize(self::$_layout->wipeSelect()));
			}
		}
		else
			self::$_layout = block\hypercollection::get()->load();
	}
	public static function placeRef($ref, $args = null)
	{
		$found = false;
		if(self::$_layout->count())
		{
			if(is_array($blocks = self::$_layout->getBlocks($ref)))
			{
				foreach($blocks as $block)
				{
					$block->setFlag(self::$_layout->getRouteFlag());
					$block->setRouteUserFlag(self::$_layout->getRouteUserFlag());
					$block->setRouteId(self::$_layout->getRouteId());
					$block->renderLayout($args);
					$found = true;
				}
			}
		}
		if(!$found)
			self::$_bad_ref[] = $ref;
	}
	final public static function run($env = null)
	{
		$cached = false;
		self::$_env = is_object($env) ? $env : new stdClass();
		self::$_session_ttl = intval(self::$_env?->session_ttl) ?: self::SESSION_TTL;
		// self::$_session_ttl = self::SESSION_TTL;
		$page_key = db::REDIS_CONTENT_PREFIX.str_replace(DIRECTORY_SEPARATOR,'_',
			trim($_SERVER['REQUEST_URI'], " #&?\n\r\t\v\x00\/"));
		if(!(self::$_redis = db::get()->redis()))
		{
			self::$_layout_cache_allowed  = false;
			self::$_content_cache_allowed = false;
		}
		if(self::$_content_cache_allowed)
		{	
			if(self::$_redis->exists($page_key))
			{
				echo self::$_redis->get($page_key);
				$cached = true;
			}
		}
		if(!$cached)
		{
			self::_init();
			ob_start();
			self::placeRef('index');
			if(count(self::$_bad_ref))
			{
				ob_end_clean();
				echo '<p><b>Layout bad references:</b> '.implode(' • ',self::$_bad_ref).'</p>';
				include('./document_errors/404.html');
				die();
			}
			if(self::$_content_cache_allowed)// && !count(self::$_bad_ref))
			{
				$out = ob_get_clean();
				self::$_redis->setEx($page_key,	db::REDIS_CONTENT_TTL, $out);
				echo $out;
			}
			else
				echo ob_get_clean();
		}
	}
	// access data
	public static function getRequestData($scope = 'args', $name = null)
	{
		if(!is_null($scope))
		{
			if(($scope == 'request') && is_null(self::$_request_data->request))
				self::$_request_data->request = array_merge(self::$_request_data->post, self::$_request_data->args);
			return is_null($name) ?
				self::$_request_data->$scope : (array_key_exists($name, self::$_request_data->$scope) ?
				self::$_request_data->$scope[$name] : null);
		}
		else
			return self::$_request_data;
	}
	public static function getSessionAllowed()
	{
		return (self::$_session_allowed && self::$_session_started);
	}
	public static function env()
	{
		return self::$_env;
	}
}
?>