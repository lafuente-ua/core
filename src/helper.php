<?
namespace core;

class helper extends \core\core
{
	private const KEY_STR = 'A123456789Cabcdefgh8jkBmn1pqrstuvwxyz7ABCDEFGH8JKLMN6PQRSTUVWXYZ';
	public const KEY_LENGTH = 6;

	public static function keyGen($length = null)
	{
		$length = $length ?: self::KEY_LENGTH;
		$text = random_bytes($length);
		$result = '';
		for($idx=0; $idx<$length; $idx++)
			$result .= self::KEY_STR[ord($text[$idx]) & 0x3F];
		return $result;
	}
}
?>