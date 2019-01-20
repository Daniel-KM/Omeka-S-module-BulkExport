<?php
namespace BulkExport\Api\Representation;

use BulkExport\Interfaces\Configurable;
use BulkExport\Processor\Manager as ProcessorManager;
use BulkExport\Writer\Manager as WriterManager;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class ExporterRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var WriterManager
     */
    protected $writerManager;

    /**
     * @var ProcessorManager
     */
    protected $processorManager;

    /**
     * @var \BulkExport\Interfaces\Writer
     */
    protected $writer;

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
            'o-module-bulk:writer_class' => $this->writerClass(),
            'o-module-bulk:writer_config' => $this->writerConfig(),
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
    public function writerClass()
    {
        return $this->resource->getWriterClass();
    }

    /**
     * @return array
     */
    public function writerConfig()
    {
        return $this->resource->getWriterConfig() ?: [];
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
     * @return \BulkExport\Interfaces\Writer|null
     */
    public function writer()
    {
        if ($this->writer) {
            return $this->writer;
        }

        $writerClass = $this->writerClass();
        $writerManager = $this->getWriterManager();
        if ($writerManager->has($writerClass)) {
            $this->writer = $writerManager->get($writerClass);
            if ($this->writer instanceof Configurable) {
                $config = $this->writerConfig();
                $this->writer->setConfig($config);
            }
        }

        return $this->writer;
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
     * @return WriterManager
     */
    protected function getWriterManager()
    {
        if (!$this->writerManager) {
            $this->writerManager = $this->getServiceLocator()->get(WriterManager::class);
        }
        return $this->writerManager;
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
