<?php
namespace Import\Api\Representation;

use Import\Interfaces\Configurable;
use Import\Processor\Manager as ProcessorManager;
use Import\Reader\Manager as ReaderManager;
use Omeka\Api\Adapter\AdapterInterface;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Entity\EntityInterface;

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
     * @var ReaderManager
     */
    protected $reader;

    /**
     * @var ProcessorManager
     */
    protected $processor;

    public function __construct(EntityInterface $resource, AdapterInterface $adapter)
    {
        parent::__construct($resource, $adapter);

        $serviceLocator = $this->getServiceLocator();
        $this->setReaderManager($serviceLocator->get(ReaderManager::class));
        $this->setProcessorManager($serviceLocator->get(ProcessorManager::class));
    }

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

    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return ReaderManager
     */
    public function getReaderManager()
    {
        return $this->readerManager;
    }

    /**
     * @param ReaderManager $readerManager
     * @return $this
     */
    public function setReaderManager(ReaderManager $readerManager)
    {
        $this->readerManager = $readerManager;
        return $this;
    }

    /**
     * @return ProcessorManager
     */
    public function getProcessorManager()
    {
        return $this->processorManager;
    }

    /**
     * @param ProcessorManager $processorManager
     * @return $this
     */
    public function setProcessorManager(ProcessorManager $processorManager)
    {
        $this->processorManager = $processorManager;
        return $this;
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

    public function getReader()
    {
        if ($this->reader) {
            return $this->reader;
        }

        $this->reader = $this->getReaderManager()->getPlugin($this->getReaderName());
        if ($this->reader instanceof Configurable) {
            $this->reader->setConfig($this->getReaderConfig());
        }

        return $this->reader;
    }

    public function getProcessor()
    {
        if ($this->processor) {
            return $this->processor;
        }

        $this->processor = $this->getProcessorManager()->getPlugin($this->getProcessorName());
        if ($this->processor instanceof Configurable) {
            $this->processor->setConfig($this->getProcessorConfig());
        }

        return $this->processor;
    }
}
