<?
namespace core\translate;

class collection extends \core\collection
{
	protected static $_model_class    = __NAMESPACE__.'\model';
	protected static $_model_table    = 'translate';

	public function getNewAlias()
	{
		$query = self::$_sql->query('select max(`alias`) as alias from translate');
		if($data = $query->fetch_object())
			return intval($data->alias)+1;
		return null;
	}
	public function checkTranslation($text)
	{
		if($found = $this->unload()
			->adWhere('!`locked`')
			->addWhere('text',$text)
			->addWhere('lang','X')
			->load()
			->getFirstItem())
		{
			$langs = array();
			$alias = $found->getAlias();
			if($translation = $this->unload()
				->addWhere('alias',$alias)
				->addWhere('`lang` != "X"')
				->adWhere('!`locked`')
				->load()
				->getAllItems())
			{
				foreach($translation as $trs)
					$langs[] = $trs->getLang();
				return $langs;
			}
		}
		return null;
	}
	public function cleanup($lang,$from)		// с языка Х
	{
		if($lng_from = $this->unload()
			->addWhere('lang','X')
			->addWhere('!`locked`')
			->addWhere('text',$from)
			->load(1)->getFirstItem())			// существующий перевод
		{
			$alias = $lng_from->getAlias(); 
			if($lng_to = $this->unload()
				->addWhere('lang',$lang)
				->addWhere('!`locked`')
				->addWhere('alias',$alias)
				->load(1)->getFirstItem())		// запись для существующего языка
			{
				$lng_to->setLocked(1)->save();	// зачистка
			}
		}
		return $this;
	}
	public function update($lang,$from,$text)		// с языка Х
	{
		if($lng_from = $this->unload()
			->addWhere('lang','X')
			->addWhere('!`locked`')
			->addWhere('text',$from)
			->load(1)->getFirstItem())	// обновление существующего перевода
		{
			$alias = $lng_from->getAlias(); 
			if($lng_to = $this->unload()
				->addWhere('lang',$lang)
				->addWhere('!`locked`')
				->addWhere('alias',$alias)
				->load(1)->getFirstItem())	// обновление существующего языка
			{
				$lng_to->setText($text)->setLocked(0)->save();
			}
			else		// новый язык существующего перевода
			{
				$lng_to = $this->getInstance($this->_class_name);
				$lng_to->setAlias($alias)
					->setLang($lang)
					->setText($text)
					->save(true);
			}
		}
		else	// новый перевод
		{
			if($last = $this->unload()
				->addWhere('!`locked`')
				->addOrder('alias','desc')
				->load(1)->getFirstItem())
				$alias = 1 + $last->getAlias();
			else
				$alias = 1;		// первая запись в словаре
			$lng_from = $this->getInstance($this->_class_name);
			$lng_from->setAlias($alias)
				->setLang('X')
				->setText($from)
				->save(true);
			$lng_from->unload()
				->setAlias($alias)
				->setLang($lang)
				->setText($text)
				->save(true);
		}
		return $this;
	}
}
?>