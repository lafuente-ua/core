<?
namespace core\translate;

class multicollection extends collection	// перевод на все языки
{
	protected static $_model_class    = __NAMESPACE__.'\hypermodel';

	const LANG_FROM = 'X';
	const LANG_DEFAULT = 'EN';

	protected function _makeSelectFields()
	{
		return 'lngb.`text` as text,
				lngb.`lang` as lang,
				lngb.`alias` as alias';
	}
	protected function _makeSelectTables()
	{
		return 'translate as lnga
			left join translate as lngb
				on lnga.`alias` = lngb.`alias`
				and lngb.`lang` != "'.self::LANG_FROM.'"
				and !lngb.`locked`';
	}
	public function getTranslated($text, $chars = 0)
	{
		$result = (object)['alias'=>null,'text'=>[]];
		foreach(core_translate_model::getSupported() as $lng)
			$result->text[$lng] = ($lng == self::LANG_DEFAULT) ? $text : '';
		if($data = $this->unload()
			->addWhere('lnga.`lang` = "'.self::LANG_FROM.'"')
			->addWhere('lnga.`text` = "'.($chars ? mb_substr($text,0,$chars,'utf-8') : $text).'"')
			->addWhere('!lnga.`locked`')
			->addOrderRecord('lngb.`lang` desc')
			->load()
			->getAllItems())
		{
			foreach($data as $var)
			{
				if(array_key_exists($var->getLang(),$result->text))
				{
					$result->text[$var->getLang()] = $var->getText();
					$result->alias = $var->getAlias();
				}
			}
		}
		return $result;
	}
}