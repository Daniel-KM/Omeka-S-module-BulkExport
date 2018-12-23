<?php

namespace BulkImport;

require_once __DIR__ . '/src/Module/AbstractGenericModule.php';

use BulkImport\Processor\ItemsProcessor;
use BulkImport\Reader\CsvReader;
use BulkImport\Module\AbstractGenericModule;
use Zend\EventManager\SharedEventManagerInterface;

class Module extends AbstractGenericModule
{
    /**
     * Attach listeners to events.
     *
     * @param SharedEventManagerInterface $sharedEventManager
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            self::class,
            'readers',
            [$this, 'getReaders']
        );
        $sharedEventManager->attach(
            self::class,
            'processors',
            [$this, 'getProcessors']
        );
    }

    public function getReaders()
    {
        return [
            'csv' => CsvReader::class,
        ];
    }

    public function getProcessors()
    {
        return [
            'items' => ItemsProcessor::class,
        ];
    }
}
