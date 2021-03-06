<?php
namespace Exceedone\Exment\Services\ViewFilter\Items\DayMonth;

use Exceedone\Exment\Enums\FilterOption;

class DayThisMonth extends DayMonthBase
{
    public static function getFilterOption()
    {
        return FilterOption::DAY_THIS_MONTH;
    }

    protected function getTargetDay($query_value)
    {
        return new \Carbon\Carbon('first day of this month');
    }
}
