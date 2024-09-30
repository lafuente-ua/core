<?
namespace core;
use core\database_config;
class db extends \core\core
/**MySQL and Redis engine core*/
{
	// Define constants in \core\database_config class for database connection

	public const REDIS_TTL = 30;
	public const REDIS_CONFIG_TTL     = 30;
	public const REDIS_DATA_TTL       = 30;
	public const REDIS_CONTENT_TTL    = 30;
	public const REDIS_LAYOUT_TTL     = 30;
	public const REDIS_TABLE_TTL      = 30;
	public const REDIS_COLLECTION_TTL = 30;

	public const REDIS_PREFIX				= database_config::REDIS_USERPREFIX.'_';
	public const REDIS_DATA_PREFIX			= database_config::REDIS_USERPREFIX.'_dt_';
	public const REDIS_CONFIG				= database_config::REDIS_USERPREFIX.'_cf';
	public const REDIS_CONTENT_PREFIX		= database_config::REDIS_USERPREFIX.'_pg_';
	public const REDIS_LAYOUT_PREFIX		= database_config::REDIS_USERPREFIX.'_lt_';
	public const REDIS_TABLE_PREFIX			= database_config::REDIS_USERPREFIX.'_tb_';
	public const REDIS_COLLECTION_PREFIX	= database_config::REDIS_USERPREFIX.'_cl_';

	private const MYSQL_TIMEOUT				= 1;
	private const MYSQL_TIMEOUT_RATIO		= 3;					// for second and others attempts

	private $_sql = null;
	private $_redis = null;
	private $_sql_problems = false;
	private $_sql_attempts = 10;
	protected static $_autosingleton = true;
	protected $_redis_publish = [];

	public function _construct($arg = null)
	{
		if($this->isSingleton())
		{
			if(database_config::MYSQL_USER)
			{
				do 
				{
					$this->_sql = new \mysqli(database_config::MYSQL_HOST, database_config::MYSQL_USER, database_config::MYSQL_PASSWORD, database_config::MYSQL_BASE);
					if($this->_sql->connect_errno)
					{
						$this->_sql->close();
						sleep(self::MYSQL_TIMEOUT * ($this->_sql_problems ? self::MYSQL_TIMEOUT_RATIO : 1));
						$this->_sql_problems = true;
					}
					$this->_sql_attempts--;
				} while($this->_sql->connect_errno && ($this->_sql_attempts > 0));
				if($this->_sql->connect_errno)
				{
					include('./core/errors/50a.html');
					die("<p>Database error:<br/><b>".self::$sql->connect_error.".</b><br/>Please try later.</p>");
				}
				$this->_sql->query("Set character_set_client = ".database_config::MYSQL_ENCODE);
				$this->_sql->query("Set character_set_connection = ".database_config::MYSQL_ENCODE);
				$this->_sql->query("Set character_set_results = ".database_config::MYSQL_ENCODE);
				$this->_sql->query("Set collation_connection = ".database_config::MYSQL_ENCODE_TBL);
			}
			if(database_config::REDIS_USER)
			{
				$connected = false;
				$this->_redis = new \Redis();
				try
				{
					if($this->_redis->connect(database_config::REDIS_HOST, database_config::REDIS_PORT))
						$connected = $this->_redis->auth([database_config::REDIS_USER, database_config::REDIS_PASSWORD]);
				}
				catch (Exception $err) {}
				finally
				{
					if(!$connected)
					{
						$this->_redis = null;
						include('./core/500.html');
						die("<p>Redis error. Please try later.</p>");
					}
					else
						$this->_redis->setOption(\Redis::OPT_SERIALIZER,\Redis::SERIALIZER_NONE);
				}
			}
		}
		else
			throw new \Exception('Object of Class "\core\db" must be a Singleton.');
	}
	public function _destruct()
	{
		$this->redisPublishCommit();
	}
	public function redis()
	{
		return $this->_redis;
	}
	public function sql()
	{
		return $this->_sql;
	}
	/** Place $value to $channel overriding previews. Publish by descructor, by redisPublishCommit() or with $forced */
	public function redisPublish($channel, $value, $forced = false)
	{
		if($forced)
			$this->_redis->publish($channel, $value);
		else
			$this->_redis_publish[$channel] = $value;
		return $this;
	}
	public function redisPublishCommit()
	{
		foreach($this->_redis_publish as $channel => $value)
			$this->_redis->publish($channel, $value);
		return $this;
	}
	public function redisPublishDispose()
	{
		$this->_redis_publish = [];
		return $this;
	}
}
?>