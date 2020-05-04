<?php
namespace Exceedone\Exment\Services\Plugin;

use Exceedone\Exment\Model\CustomValue;

/**
 * Plugin (Trigger) base class
 */
class PluginTriggerBase
{
    use PluginBase, PluginEventTrait, PluginButtonTrait;
    
    public $custom_table;
    public $custom_value;
    public $isCreate;

    public function __construct($plugin, $custom_table, $custom_value, $options = [])
    {
        $this->_initButton($plugin, $custom_table, $custom_value, $options);
        $this->_initEvent($plugin, $custom_table, $custom_value, $options);
    }

    public function execute()
    {
    }
}
