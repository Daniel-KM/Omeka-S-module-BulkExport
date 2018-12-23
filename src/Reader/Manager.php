<?php
namespace BulkImport\Reader;

use BulkImport\AbstractPluginManager;
use BulkImport\Interfaces\Reader;

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
