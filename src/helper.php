<?
namespace core;

class helper extends \core\core
{
	private const KEY_STR = 'A123456789Cabcdefgh8jkBmn1pqrstuvwxyz7ABCDEFGH8JKLMN6PQRSTUVWXYZ';
	public const KEY_LENGTH = 6;

	public static function keyGen($length = null)
	{
		$length = $length ?: self::KEY_LENGTH;
		$text = substr(sha1(microtime().random_int(99999999,PHP_INT_MAX)),random_int(1,40-$length*2));
		$result = '';
		for($idx=0; $idx<($length*2); $idx+=2)
			$result .= self::KEY_STR[hexdec(substr($text,$idx,2)) & 0x2F];
		return $result;
	}
}
?>