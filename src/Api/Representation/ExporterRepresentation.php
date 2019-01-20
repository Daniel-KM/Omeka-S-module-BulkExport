<?php
namespace BulkExport\Api\Representation;

use BulkExport\Interfaces\Configurable;
use BulkExport\Processor\Manager as ProcessorManager;
use BulkExport\Reader\Manager as ReaderManager;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class ExporterRepresentation extends AbstractEntityRepresentation
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
     * @var \BulkExport\Interfaces\Reader
     */
    protected $reader;

    /**
     * @var \BulkExport\Interfaces\Processor
     */
    protected $processor;

    public function getControllerName()
    {
        return 'exporter';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();

        return [
            'o:id' => $this->id(),
            'o:label' => $this->label(),
            'o-module-bulk:reader_class' => $this->readerClass(),
            'o-module-bulk:reader_config' => $this->readerConfig(),
            'o-module-bulk:processor_class' => $this->processorClass(),
            'o-module-bulk:processor_config' => $this->processorConfig(),
            'o:owner' => $owner ? $owner->getReference() : null,
        ];
    }

    public function getJsonLdType()
    {
        return 'o-module-bulk:Exporter';
    }

    /**
     * @return \BulkExport\Entity\Exporter
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return string
     */
    public function label()
    {
        return $this->resource->getLabel();
    }

    /**
     * @return string
     */
    public function readerClass()
    {
        return $this->resource->getReaderClass();
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
    public function processorClass()
    {
        return $this->resource->getProcessorClass();
    }

    /**
     * @return array
     */
    public function processorConfig()
    {
        return $this->resource->getProcessorConfig() ?: [];
    }

    /**
     * Get the owner of this exporter.
     *
     * @return \Omeka\Api\Representation\UserRepresentation|null
     */
    public function owner()
    {
        return $this->getAdapter('users')
            ->getRepresentation($this->resource->getOwner());
    }

    /**
     * @return \BulkExport\Interfaces\Reader|null
     */
    public function reader()
    {
        if ($this->reader) {
            return $this->reader;
        }

        $readerClass = $this->readerClass();
        $readerManager = $this->getReaderManager();
        if ($readerManager->has($readerClass)) {
            $this->reader = $readerManager->get($readerClass);
            if ($this->reader instanceof Configurable) {
                $config = $this->readerConfig();
                $this->reader->setConfig($config);
            }
        }

        return $this->reader;
    }

    /**
     * @return \BulkExport\Interfaces\Processor|null
     */
    public function processor()
    {
        if ($this->processor) {
            return $this->processor;
        }

        $processorClass = $this->processorClass();
        $processorManager = $this->getProcessorManager();
        if ($processorManager->has($processorClass)) {
            $this->processor = $processorManager->get($processorClass);
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

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/bulk/id',
            [
                'controller' => $this->getControllerName(),
                'action' => $action,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }
}
