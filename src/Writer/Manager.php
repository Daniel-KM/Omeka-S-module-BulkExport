<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\AbstractPluginManager;

class Manager extends AbstractPluginManager
{
    protected function getName()
    {
        return 'writers';
    }

    protected function getInterface()
    {
        return WriterInterface::class;
    }
}
