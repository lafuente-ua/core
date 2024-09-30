<?
namespace core\translate;

class hypercollection extends collection
{
	protected const PLURAL_KEY = 'qty';
	protected const REPLACEMENT_MARKER = '@';
	protected static $_model_class    = __NAMESPACE__.'\hypermodel';
	protected static $_model_table    = 'translate';

	protected function _makeSelectFields()
	{
		return 'lngb.`text` as text';
	}
	protected function _makeSelectTables()
	{
		return 'translate_idx as lnga use index (`idx`)
			left join translate as lngb use index (`idx`)
				on lnga.`id` = lngb.`alias`
				and !lngb.`locked`';
	}
	/**Return translated text in plural form to current language*/
	protected function _getTranslated($text, $plural = null)
	{
		if(($data = $this->unload()
			->addWhere('lnga.`text` = "'.$text.'"')
			->addWhere('lngb.`lang` = "'.$this->getCurrentLanguage().$plural.'"')
			->addWhere('!lnga.`locked`')
			->load()
			->getFirstItem()) && !is_null($data->getText()))
			return $data->getText();
		else
			return model::GOOGLE_ALLOWED_AUTO ? $this->googleTranslate($text) : $text;
	}
	/**Return translated text to current language with replacements*/
    public function getTranslated($text = null, $replace = [])
	{
		$qty = array_key_exists(self::PLURAL_KEY, $replace) ? $replace[self::PLURAL_KEY] : null;
		return is_null($text) ? null : 
			$this->_makeReplacements($this->_getTranslated($text, is_null($qty) ? null : 
				$this->_getPluralForm($qty)), $replace);
    }
	/**Return plural form id by qty for current language*/
	protected function _getPluralForm($qty)
	{
		$form = 0;
		switch($this->getCurrentLanguage())
		{
			case 'ru':
			case 'uk':
				$limits = [0,1,4,20];
				$form = 4;
				foreach($limits as $key => $val)
				{
					if($val >= $qty)
					{
						$form = $key;
						break;
					}
				}
				$form = ($form > 3) ? $this->_getPluralForm(1 + (($qty - 1) % 10)) : $form;
				break;
			case 'en':
			default:
				$form = 1 + ($qty <=> 1);
				break;
		}
		return $form;
	}
	/**Create keylist and substitute text by tham*/
    protected static function _makeReplacements($text, array $replace)
    {
        if(empty($replace)) 
            return $text;
        $shouldReplace = [];
        foreach ($replace as $key => $value) 
		{
            $shouldReplace[self::REPLACEMENT_MARKER.ucfirst($key)] = mb_convert_case($value, MB_CASE_TITLE);
            $shouldReplace[self::REPLACEMENT_MARKER.strtoupper($key)] = mb_strtoupper($value);
            $shouldReplace[self::REPLACEMENT_MARKER.$key] = $value;
        }
        return strtr($text, $shouldReplace);
    }
	/**Return GoogleAPI translated text to current language*/
	public function googleTranslate($text)
	{
		if(model::GOOGLE_ALLOWED 
			&& (($current = strtolower($this->getCurrentLanguage())) != 'en'))
		{
			$data = (object)['q' => $text,
				'source' => 'en',
				'target' => ($current == 'ua') ? 'uk' : $current,
				'format' => 'text'];
			$url = model::GOOGLE_API_URL.'?key='.model::GOOGLE_API_KEY;
			$curl = curl_init($url);
			curl_setopt_array($curl,
				[CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => json_encode($data),
				CURLOPT_HTTPHEADER => array('Content-Type:application/json'),
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 2,
				CURLOPT_CONNECTTIMEOUT => model::GOOGLE_TIMEOUT,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_TIMEOUT => 5000,
				CURLOPT_REFERER => $url,
				CURLOPT_HEADER => false,
				CURLOPT_RETURNTRANSFER => true]);
			$data_out = json_decode($edata = curl_exec($curl));
			curl_close($curl);
			if($data_out && $data_out->data && $data_out->data->translations)
				return $data_out?->data?->translations[0]?->translatedText;
		}
		return $text;
	}
}