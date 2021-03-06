<?php

namespace Exceedone\Exment\Tests\Feature;

use Encore\Admin\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Exceedone\Exment\Enums\PluginType;
use Exceedone\Exment\Enums\NotifyTrigger;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Model\Notify;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Services\DataImportExport;
use Exceedone\Exment\Services\NotifyService;
use Exceedone\Exment\Tests\TestDefine;
use Exceedone\Exment\Tests\TestTrait;
use Exceedone\Exment\Tests\PluginTestTrait;


class PluginTest extends TestCase
{
    use TestTrait, PluginTestTrait;

    protected function init(bool $fake)
    {
        $this->initAllTest();
        $this->be(LoginUser::find(TestDefine::TESTDATA_USER_LOGINID_USER1));
    }


    /**
     * test plugin button
     *
     * @return void
     */
    public function testButton()
    {
        $custom_table = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_EDIT_ALL);
        $custom_value = $custom_table->getValueModel()->where('value->multiples_of_3', '1')->first();
        
        list($plugin, $pluginClass) = $this->getPluginInfo('TestPluginButton', PluginType::BUTTON, [
            'custom_table' => $custom_table,
            'custom_value' => $custom_value,
        ]);

        $data = $pluginClass->execute();
        $this->assertTrue(array_get($data, 'result'));
        $this->assertEquals(array_get($data, 'swaltext'), '正常です。');
    }

    /**
     * test plugin event saved
     *
     * @return void
     */
    public function testEventSaved()
    {
        $id = 3;

        DB::beginTransaction();

        try {
            $old_value = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_VIEW_ALL)->getValueModel($id);
            $old_int = $old_value->getValue('integer');

            $custom_table = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_EDIT_ALL);
            $custom_value = $custom_table->getValueModel($id);
            $change_val = $custom_value->getValue('multiples_of_3') == '1'? '0': '1';
            $custom_value->setValue('multiples_of_3', $change_val);
            $custom_value->save();

            $new_value = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_VIEW_ALL)->getValueModel($id);
            $new_int = $new_value->getValue('integer');

            $this->assertEquals($old_int + 100, $new_int);
        } finally {
            DB::rollback();
        }
    }

    /**
     * test plugin event workflow executed
     *
     * @return void
     */
    public function testEventWorkflow()
    {
        $id = 4;

        DB::beginTransaction();

        try {
            $custom_table = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_EDIT);
            $custom_value = $custom_table->getValueModel($id);

            // get action
            $action = $custom_value->getWorkflowActions()->first();
            $action_user = $action->getAuthorityTargets($custom_value)->first();
            $this->be(LoginUser::find($action_user->id));
            
            $action->executeAction($custom_value, [
                'comment' => 'プラグインのワークフローイベントのテストです。',
            ]);

            $new_text = $custom_value->getValue('init_text');

            $this->assertEquals($new_text, 'workflow executed');
        } finally {
            DB::rollback();
        }
    }

    /**
     * test plugin event notify executed
     *
     * @return void
     */
    public function testEventNotify()
    {
        $id = 5;

        DB::beginTransaction();

        try {
            $custom_table = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_VIEW);
            $custom_value = $custom_table->getValueModel($id);

            $notify = Notify::where('custom_table_id', $custom_table->id)->where('notify_trigger', NotifyTrigger::BUTTON)->first();
            $target_user = CustomTable::getEloquent('user')->getValueModel(TestDefine::TESTDATA_USER_LOGINID_USER2);

            NotifyService::executeNotifyAction($notify, [
                'custom_value' => $custom_value,
                'subject' => 'プラグインテスト',
                'body' => 'プラグインの通知イベントのテストです。',
                'user' => $target_user,
            ]);

            $new_text = $custom_value->getValue('init_text');

            $this->assertEquals($new_text, 'notify executed');
        } finally {
            DB::rollback();
        }
    }

    /**
     * test plugin batch
     *
     * @return void
     */
    public function testEventBatch()
    {
        $id = 3;

        DB::beginTransaction();

        try {
            $custom_table = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_EDIT);
            $custom_value = $custom_table->getValueModel($id);
            $custom_value->delete();

            $trash_value = $custom_table->getValueModel()->withTrashed()->find($id);
            $this->assertTrue(isset($trash_value));

            \Artisan::call('exment:batch', ['--name' => 'TestPluginBatch']);

            $trash_value = $custom_table->getValueModel()->withTrashed()->find($id);
            $this->assertTrue(is_null($trash_value));
        } finally {
            DB::rollback();
        }
    }

    /**
     * test plugin validate
     *
     * @return void
     */
    public function testValidate()
    {
        $custom_table = CustomTable::getEloquent(TestDefine::TESTDATA_TABLE_NAME_EDIT_ALL);
        $custom_value = $custom_table->getValueModel(1);
        
        list($plugin, $pluginClass) = $this->getPluginInfo('TestPluginValidator', PluginType::VALIDATOR, [
            'custom_table' => $custom_table,
            'custom_value' => $custom_value,
            'input_value' => [
                'integer' => 9999999999,
                'currency' => 9999999999,
            ],
        ]);

        $result = $pluginClass->validate();
        $this->assertTrue($result);
    }

    /**
     * test plugin import
     *
     * @return void
     */
    public function testImport()
    {
        DB::beginTransaction();
        try {
            $pre_cnt = getModelName('parent_table')::where('value->init_text', 'plugin_unit_test')->count();

            $import_path = storage_path(path_join_os('app', 'import', 'unittest'));
            if (\File::exists($import_path)) {
                \File::deleteDirectory($import_path);
            }
            \File::makeDirectory($import_path, 0755, true);
            $source_path = exment_package_path("tests/tmpfile/Feature/plugin_import");
            \File::copyDirectory($source_path, $import_path);
            $files = \File::files($import_path);
    
            $plugin = Plugin::where('plugin_name', 'TestPluginImport')->first();

            $service = (new DataImportExport\DataImportExportService());
            $res = $this->callProtectedMethod($service, 'customImport', $plugin->id, $files[0]);

            $this->assertTrue(array_get($res, 'result'));

            $parent = getModelName('parent_table')::where('value->init_text', 'plugin_unit_test')->get();
            $this->assertEquals($pre_cnt+1, count($parent));

            $parent = $parent->last();

            $child_cnt = getModelName('child_table')::where('parent_type', 'parent_table')
                ->where('parent_id', $parent->id)->count();
            $this->assertEquals(2, $child_cnt);
        } finally {
            DB::rollback();
        }
    }

    /**
     * test plugin export csv
     *
     * @return void
     */
    public function testExportCsv()
    {
        $plugin = Plugin::where('plugin_name', 'TestPluginExportCsv')->first();
        $pluginClass = $plugin->getClass(PluginType::EXPORT);

        list($plugin, $pluginClass) = $this->getPluginInfo('TestPluginExportCsv', PluginType::EXPORT);

        $custom_table = CustomTable::getEloquent('information');
        $custom_view = CustomView::getAllData($custom_table);
        $classname = getModelName($custom_table);
        $grid = new Grid(new $classname);

        $pluginClass->defaultProvider(new DataImportExport\Providers\Export\DefaultTableProvider([
            'custom_table' => $custom_table,
            'grid' => $grid
        ]));

        $pluginClass->viewProvider(new DataImportExport\Providers\Export\SummaryProvider([
            'custom_table' => $custom_table,
            'custom_view' => $custom_view,
            'grid' => $grid
        ]));

        $file = null;
        try {
            $file = $pluginClass->execute();
            $this->assertTrue(isset($file));
            $this->assertTrue(\File::exists($file));
        }
        // Delete if exception
        finally {
            if (isset($file) && is_string($file) && \File::exists($file)) {
                \File::delete($file);
            }
        }
    }

    /**
     * test plugin export excel
     *
     * @return void
     */
    public function testExportExcel()
    {
        list($plugin, $pluginClass) = $this->getPluginInfo('TestPluginExportExcel', PluginType::EXPORT);

        $custom_table = CustomTable::getEloquent('information');
        $custom_view = CustomView::getAllData($custom_table);
        $classname = getModelName($custom_table);
        $grid = new Grid(new $classname);

        $pluginClass->defaultProvider(new DataImportExport\Providers\Export\DefaultTableProvider([
            'custom_table' => $custom_table,
            'grid' => $grid
        ]));

        $pluginClass->viewProvider(new DataImportExport\Providers\Export\SummaryProvider([
            'custom_table' => $custom_table,
            'custom_view' => $custom_view,
            'grid' => $grid
        ]));

        $file = null;
        try {
            $file = $pluginClass->execute();
            $this->assertTrue(isset($file));
            $this->assertTrue(\File::exists($file));
        }
        // Delete if exception
        finally {
            if (isset($file) && is_string($file) && \File::exists($file)) {
                \File::delete($file);
            }
        }
    }

    /**
     * test plugin button
     *
     * @return void
     */
    public function testDocument()
    {
        $custom_table = CustomTable::getEloquent(SystemTableName::USER);
        $custom_value = $custom_table->getValueModel()->latest()->first();

        list($plugin, $pluginClass) = $this->getPluginInfo('TestPluginDocument', PluginType::DOCUMENT, [
            'custom_table' => $custom_table,
            'id' => $custom_value->id,
        ]);

        $response = $pluginClass->execute();
        $this->assertTrue(array_get($response, 'result'));

        $file = ExmentFile::latest()->first();
        $this->assertTrue(isset($file));
        $this->assertEquals(SystemTableName::USER, $file->parent_type);
        $this->assertEquals($custom_value->id, $file->parent_id);
        $this->assertTrue(Storage::disk(config('admin.upload.disk'))->exists($file->path));
    }
}
