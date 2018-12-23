<?php
namespace Import\Reader;

use Import\AbstractPluginManager;
use Import\Interfaces\Reader;

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
