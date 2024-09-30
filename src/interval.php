<?
namespace core;

class interval
{
	protected $_c_days = 0;
	protected $_c_hours = 0;
	protected $_c_mins = 0;
	protected $_c_seconds = 0;

	protected static $_delimiter = '_days';
	protected static $_core = null;

	public function __construct($seconds)
	{
		if($this->_c_days = floor($seconds / 86400))
			$seconds -= 86400 * $this->_c_days;
		if($this->_c_hours = floor($seconds / 3600))
			$seconds -= 3600 * $this->_c_hours;
		if($this->_c_mins = floor($seconds / 60))
			$seconds -= 60 * $this->_c_mins;
		$this->_c_seconds = $seconds;
		if(is_null(self::$_core))
			self::$_core = core::get();
	}
	public static function delimiter($delimiter)
	{
		self::$_delimiter = $delimiter;
	}
	public function __toString()
	{
		return $this->toString();
	}
	public function toString($leading_zero = false, $seconds = true)
	{
		if($seconds)
		{
			if($this->_c_days)
			{
				return sprintf(self::$_core->_(self::$_delimiter,['qty' => $this->_c_days]).' %02u:%02u:%02u',
					$this->_c_days, $this->_c_hours, $this->_c_mins, $this->_c_seconds);
			}
			elseif($this->_c_hours)
				return sprintf($leading_zero ? '%02u:%02u:%02u' : '%2u:%02u:%02u', 
					$this->_c_hours, $this->_c_mins, $this->_c_seconds);
			else
				return sprintf($leading_zero ? '%02u:%02u' : '%2u:%02u', 
					$this->_c_mins, $this->_c_seconds);
		}
		else
		{
			if($this->_c_days)
			{
				return sprintf(self::$_core->_(self::$_delimiter,['qty' => $this->_c_days]).' %02u:%02u',
					$this->_c_days, $this->_c_hours, $this->_c_mins);
			}
			elseif($this->_c_hours)
				return sprintf($leading_zero ? '%02u:%02u' : '%2u:%02u', 
					$this->_c_hours, $this->_c_mins);
			else
				return sprintf($leading_zero ? '00:%02u' : '0:%2u', 
					$this->_c_mins);
		}
	}
}
?>