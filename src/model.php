<?
namespace core;

/** Model base class */
class model extends \core
{
	public function make()
	{
		echo 'Make some else '.self::CORE_NAME;
	}
}
