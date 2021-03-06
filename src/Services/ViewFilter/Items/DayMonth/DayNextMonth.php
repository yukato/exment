<?php
namespace Exceedone\Exment\Services\ViewFilter\Items\DayMonth;

use Exceedone\Exment\Enums\FilterOption;

class DayNextMonth extends DayMonthBase
{
    public static function getFilterOption()
    {
        return FilterOption::DAY_NEXT_MONTH;
    }

    protected function getTargetDay($query_value)
    {
        return new \Carbon\Carbon('first day of next month');
    }
}
