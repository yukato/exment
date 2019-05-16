<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Facades\Admin;
use Encore\Admin\Grid\Tools\RefreshButton;
use Exceedone\Exment\Form\Tools;

trait CustomValueCalendar
{
    protected function gridCalendar()
    {
        $table_name = $this->custom_table->table_name;
        $model = $this->getModelNameDV()::query();
        \Exment::user()->filterModel($model, $table_name, $this->custom_view);

        $tools = [];
        $tools[] = new Tools\GridChangePageMenu('data', $this->custom_table, false);
        $tools[] = new Tools\GridChangeView($this->custom_table, $this->custom_view);
        $tools[] = new RefreshButton();

        return view('exment::widgets.calendar', [
            'view_id' => $this->custom_view->suuid,
            'data_url' => admin_url('webapi/data', [$this->custom_table->table_name, 'calendar']),
            'createUrl' => admin_url("data/$table_name/create"),
            'new' => trans('admin.new'),
            'tools' => $tools,
            'locale' => \App::getLocale(),
        ]);
    }
}
