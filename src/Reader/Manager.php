<?php
namespace BulkExport\Reader;

use BulkExport\AbstractPluginManager;
use BulkExport\Interfaces\Reader;

class Manager extends AbstractPluginManager
{
    protected function getName()
    {
        return 'readers';
    }

    protected function getInterface()
    {
        return Reader::class;
    }
}
