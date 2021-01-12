<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Exceedone\Exment\ColumnItems\CustomItem;
use Exceedone\Exment\Form\Field;
use Exceedone\Exment\Validator;
use Encore\Admin\Grid\Filter;

class Boolean extends CustomItem
{
    use ImportValueTrait;

    /**
     * laravel-admin set required. if false, always not-set required
     */
    protected $required = false;
    
    protected function _text($v)
    {
        if (array_get($this->custom_column, 'options.true_value') == $v) {
            return array_get($this->custom_column, 'options.true_label');
        } elseif (array_get($this->custom_column, 'options.false_value') == $v) {
            return array_get($this->custom_column, 'options.false_label');
        }
        return null;
    }

    public function saving()
    {
        if (is_null($this->value)) {
            return array_get($this->custom_column, 'options.false_value');
        }
    }

    protected function getAdminFieldClass()
    {
        return Field\SwitchField::class;
    }
    
    protected function getAdminFilterClass()
    {
        return Filter\Equal::class;
    }
 
    protected function setValidates(&$validates, $form_column_options)
    {
        $option = $this->getImportValueOption();
        $validates[] = new Validator\BooleanRule($option);
    }

    protected function setAdminOptions(&$field, $form_column_options)
    {
        $options = $this->custom_column->options;
        
        // set options
        $states = [
            'on'  => ['value' => array_get($options, 'true_value'), 'text' => array_get($options, 'true_label')],
            'off' => ['value' => array_get($options, 'false_value'), 'text' => array_get($options, 'false_label')],
        ];
        $field->states($states);
    }
    
    protected function setAdminFilterOptions(&$filter)
    {
        $column = $this->custom_column;
        $filter->radio([
            ''   => 'All',
            array_get($column, 'options.false_value')    => array_get($column, 'options.false_label'),
            array_get($column, 'options.true_value')    => array_get($column, 'options.true_label'),
        ]);
    }
    
    protected function getRemoveValidates()
    {
        return [\Encore\Admin\Validator\HasOptionRule::class];
    }

    /**
     * replace value for import
     *
     * @return array
     */
    protected function getImportValueOption()
    {
        $column = $this->custom_column;
        return [
            array_get($column, 'options.false_value')    => array_get($column, 'options.false_label'),
            array_get($column, 'options.true_value')    => array_get($column, 'options.true_label')
        ];
    }

    /**
     * Get pure value. If you want to change the search value, change it with this function.
     *
     * @param string $label
     * @return ?string string:matched, null:not matched
     */
    public function getPureValue($label)
    {
        $option = $this->getImportValueOption();

        foreach ($option as $value => $l) {
            if (strtolower($label) == strtolower($l)) {
                return $value;
            }
        }
        return null;
    }

    
    /**
     * Set Custom Column Option Form. Using laravel-admin form option
     * https://laravel-admin.org/docs/#/en/model-form-fields
     *
     * @param Form $form
     * @return void
     */
    public function setCustomColumnOptionForm(&$form)
    {
        // yes/no ----------------------------
        $form->text('true_value', exmtrans("custom_column.options.true_value"))
            ->help(exmtrans("custom_column.help.true_value"))
            ->required();

        $form->text('true_label', exmtrans("custom_column.options.true_label"))
            ->help(exmtrans("custom_column.help.true_label"))
            ->required()
            ->default(exmtrans("custom_column.options.true_label_default"));
            
        $form->text('false_value', exmtrans("custom_column.options.false_value"))
            ->help(exmtrans("custom_column.help.false_value"))
            ->required();

        $form->text('false_label', exmtrans("custom_column.options.false_label"))
            ->help(exmtrans("custom_column.help.false_label"))
            ->required()
            ->default(exmtrans("custom_column.options.false_label_default"));
    }
}
