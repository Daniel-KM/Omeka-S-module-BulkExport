<?php
namespace BulkImport\Reader;

use BulkImport\AbstractPluginManager;
use BulkImport\Interfaces\Reader;

class Manager extends AbstractPluginManager
{
    protected function getEventName()
    {
        return 'readers';
    }

    protected function getInterface()
    {
        return Reader::class;
    }
}
