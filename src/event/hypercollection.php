<?
namespace core\event;

class hypercollection extends namespace\collection
{
	protected function _makeSelectFields()
	{
		return 'evn.`id` as id,
				evn.`name` as name,
				evn.`class` as class,
				obs.`sort` as sort,
				obs.`class` as observer,
				obs.`method` as method';
	}
	protected function _makeSelectTables()
	{
		$this->addWhere('!evn.`locked`')
			->addWhere('!obs.`locked`')
			->addOrderby('obs.`sort` asc');
		return 'events as evn
				inner join observers as obs
				on evn.`id` = obs.`event`';
	}
}
?>