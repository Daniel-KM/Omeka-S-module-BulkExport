<?php
namespace BulkImport\Api\Representation;

use BulkImport\Interfaces\Configurable;
use BulkImport\Processor\Manager as ProcessorManager;
use BulkImport\Reader\Manager as ReaderManager;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class ImporterRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var ReaderManager
     */
    protected $readerManager;

    /**
     * @var ProcessorManager
     */
    protected $processorManager;

    /**
     * @var \BulkImport\Interfaces\Reader
     */
    protected $reader;

    /**
     * @var \BulkImport\Interfaces\Processor
     */
    protected $processor;

    public function getJsonLd()
    {
        return [
            'o:id' => $this->getId(),
            'name' => $this->getName(),
            'reader_name' => $this->getReaderName(),
            'reader_config' => $this->getReaderConfig(),
            'processor_name' => $this->getProcessorName(),
            'processor_config' => $this->getProcessorConfig(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o-module-import:Importer';
    }

    /**
     * @return \BulkImport\Entity\Importer
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return ReaderManager
     */
    public function getReaderManager()
    {
        if (!$this->readerManager) {
            $this->readerManager = $this->getServiceLocator()->get(ReaderManager::class);
        }
        return $this->readerManager;
    }

    /**
     * @return ProcessorManager
     */
    public function getProcessorManager()
    {
        if (!$this->processorManager) {
            $this->processorManager = $this->getServiceLocator()->get(ProcessorManager::class);
        }
        return $this->processorManager;
    }

    /*
     * Magic getter to always pull data from resource
     */
    public function __call($method, $arguments)
    {
        if (substr($method, 0, 3) == 'get') {
            return $this->resource->$method();
        }
    }

    /**
     * @return \BulkImport\Interfaces\Reader|null
     */
    public function getReader()
    {
        if ($this->reader) {
            return $this->reader;
        }

        $readerName = $this->getReaderName();
        $readerManager = $this->getReaderManager();
        if ($readerManager->has($readerName)) {
            $this->reader = $readerManager->get($readerName);
            if ($this->reader instanceof Configurable) {
                $config = $this->getReaderConfig();
                $this->reader->setConfig($config);
            }
        }

        return $this->reader;
    }

    /**
     * @return \BulkImport\Interfaces\Processor|null
     */
    public function getProcessor()
    {
        if ($this->processor) {
            return $this->processor;
        }

        $processorName = $this->getProcessorName();
        $processorManager = $this->getProcessorManager();
        if ($processorManager->has($processorName)) {
            $this->processor = $processorManager->get($processorName);
            if ($this->processor instanceof Configurable) {
                $config = $this->getProcessorConfig();
                $this->processor->setConfig($config);
            }
        }

        return $this->processor;
    }
}
