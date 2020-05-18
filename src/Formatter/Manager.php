<?php
namespace BulkExport\Formatter;

use Omeka\ServiceManager\AbstractPluginManager;

class Manager extends AbstractPluginManager
{
    protected $instanceOf = FormatterInterface::class;
}
