<?
namespace core;

class time extends \DateTime
{
	public const FORMAT_SQL		= 'Y-m-d H:i:s';	// for create and update data
	public const FORMAT_DATE_SQL	= 'Y-m-d';
	public const FORMAT_DOM		= 'Y-m-d\TH:i';
	public const FORMAT_DEFAULT	= 'd.m.Y H:i:s';
	public const FORMAT_SHORT		= 'd.m.Y H:i';
	public const FORMAT_DATE		= 'd.m.Y';
	public const FORMAT_TIME		= 'H:i:s';
	public const FORMAT_MICROTIME	= 'H:i:s.u';
	public const FORMAT_YEAR		= 'Y';
	private const TIME_ZONE		    = 'Europe/Kiev';

	protected static $__zone = null;
	protected static $__format = null;
	protected $__xformat = null;
	public function __construct($time = "now", $timezone = null)
	{
		if(is_null($timezone))
			$timezone = self::$__zone ?: new \DateTimeZone(self::TIME_ZONE);
		parent::__construct($time, $timezone);
		self::$__format = self::FORMAT_DEFAULT;
	}
	public function setGlobalFormat($format)
	{
		if(!is_null($format))
			self::$__format = $format;
		return $this;
	}
	public function setFormat($format)
	{
		if(!is_null($format))
			$this->__xformat = $format;
		return $this;
	}
	public function unsetFormat()
	{
		$this->__xformat = NULL;
		return $this;
	}
    public function get()
    {
        return $this->format(is_null($this->__xformat) ? self::$__format : $this->__xformat);
    }
	public function __toString()
	{
		return $this->get();
	}
	/** Timestamp from today begins. Ignored timezone */
	public function daylyStamp() {
		$tme = explode(':',$this->format('G:i:s'));
		return ($tme[0]*60 +$tme[1])*60 + $tme[2];
	}
	/** Global timezone setter */
	public static function setZone($zone)
	{
		self::$__zone = new \DateTimeZone($zone);
	}
}
?>