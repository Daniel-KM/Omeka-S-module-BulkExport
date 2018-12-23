<?php
namespace Import\Processor;

use Import\AbstractPluginManager;
use Import\Interfaces\Processor;

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
