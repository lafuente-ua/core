<?
namespace core\block\route;

class collection extends \core\collection
{
	protected static $_model_class    = __NAMESPACE__.'\model';
	protected static $_model_table    = 'route';

	protected function XX_makeSelectFields()
	{
		return '`id` AS id,
				`label` AS label,
				`frontname` AS route_frontname,
				`action` AS route_action,
				`flag` AS flag';
	}
}
?>