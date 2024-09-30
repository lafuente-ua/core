<?
namespace core\translate;

class model extends \core\model
{
	public const LANG_DEFAULT = 'uk';
	public const GOOGLE_ALLOWED = true;
	public const GOOGLE_ALLOWED_AUTO = false;
	public const GOOGLE_TIMEOUT = 5000;
	public const GOOGLE_API_KEY = '<YOUR_GOOGLE_API_KEY>';
	public const GOOGLE_API_URL = 'https://translation.googleapis.com/language/translate/v2';

	protected static $_table_name = 'translate';
	protected static $_supported = ['en','uk','ru'];

	public static function isPossible($lang)
	{
		return in_array(strtolower(substr($lang,0,2)),self::$_supported);
	}
	public static function getSupported()
	{
		return self::$_supported;
	}
}
?>