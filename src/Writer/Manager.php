<?php
namespace BulkExport\Writer;

use BulkExport\AbstractPluginManager;
use BulkExport\Interfaces\Writer;

class Manager extends AbstractPluginManager
{
    protected function getName()
    {
        return 'writers';
    }

    protected function getInterface()
    {
        return Writer::class;
    }
}
