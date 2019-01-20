<?php
namespace BulkExport\Processor;

use BulkExport\AbstractPluginManager;
use BulkExport\Interfaces\Processor;

class Manager extends AbstractPluginManager
{
    protected function getName()
    {
        return 'processors';
    }

    protected function getInterface()
    {
        return Processor::class;
    }
}
