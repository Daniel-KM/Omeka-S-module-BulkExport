<?php
namespace BulkImport\Processor;

use BulkImport\AbstractPluginManager;
use BulkImport\Interfaces\Processor;

class Manager extends AbstractPluginManager
{
    protected function getEventName()
    {
        return 'processors';
    }

    protected function getInterface()
    {
        return Processor::class;
    }
}
