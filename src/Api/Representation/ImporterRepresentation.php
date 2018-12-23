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
            'o:id' => $this->id(),
            'o:name' => $this->name(),
            'o-module-bulk:reader_name' => $this->readerName(),
            'o-module-bulk:reader_config' => $this->readerConfig(),
            'o-module-bulk:processor_name' => $this->processorName(),
            'o-module-bulk:processor_config' => $this->processorConfig(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o-module-bulk:Importer';
    }

    /**
     * @return \BulkImport\Entity\Importer
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->resource->getName();
    }

    /**
     * @return string
     */
    public function readerName()
    {
        return $this->resource->getReaderName();
    }

    /**
     * @return array
     */
    public function readerConfig()
    {
        return $this->resource->getReaderConfig() ?: [];
    }

        /**
     * @return string
     */
    public function processorName()
    {
        return $this->resource->getProcessorName();
    }

    /**
     * @return array
     */
    public function processorConfig()
    {
        return $this->resource->getProcessorConfig() ?: [];
    }

/**
     * @return \BulkImport\Interfaces\Reader|null
     */
    public function reader()
    {
        if ($this->reader) {
            return $this->reader;
        }

        $readerName = $this->readerName();
        $readerManager = $this->getReaderManager();
        if ($readerManager->has($readerName)) {
            $this->reader = $readerManager->get($readerName);
            if ($this->reader instanceof Configurable) {
                $config = $this->readerConfig();
                $this->reader->setConfig($config);
            }
        }

        return $this->reader;
    }

    /**
     * @return \BulkImport\Interfaces\Processor|null
     */
    public function processor()
    {
        if ($this->processor) {
            return $this->processor;
        }

        $processorName = $this->processorName();
        $processorManager = $this->getProcessorManager();
        if ($processorManager->has($processorName)) {
            $this->processor = $processorManager->get($processorName);
            if ($this->processor instanceof Configurable) {
                $config = $this->processorConfig();
                $this->processor->setConfig($config);
            }
        }

        return $this->processor;
    }

    /**
     * @return ReaderManager
     */
    protected function getReaderManager()
    {
        if (!$this->readerManager) {
            $this->readerManager = $this->getServiceLocator()->get(ReaderManager::class);
        }
        return $this->readerManager;
    }

    /**
     * @return ProcessorManager
     */
    protected function getProcessorManager()
    {
        if (!$this->processorManager) {
            $this->processorManager = $this->getServiceLocator()->get(ProcessorManager::class);
        }
        return $this->processorManager;
    }
}
