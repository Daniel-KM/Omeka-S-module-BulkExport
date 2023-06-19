<?php declare(strict_types=1);

namespace BulkExport\Api\Representation;

use BulkExport\Interfaces\Configurable;
use BulkExport\Writer\Manager as WriterManager;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class ExporterRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var WriterManager
     */
    protected $writerManager;

    /**
     * @var \BulkExport\Writer\WriterInterface
     */
    protected $writer;

    public function getControllerName()
    {
        return 'exporter';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();
        return [
            'o:id' => $this->id(),
            'o:owner' => $owner ? $owner->getReference() : null,
            'o:label' => $this->label(),
            'o-bulk:writer_class' => $this->writerClass(),
            'o-bulk:writer_config' => $this->writerConfig(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o-bulk:Exporter';
    }

    public function owner(): ?\Omeka\Api\Representation\UserRepresentation
    {
        $user = $this->resource->getOwner();
        return $user
            ? $this->getAdapter('users')->getRepresentation($user)
            : null;
    }

    public function label(): string
    {
        return (string) $this->resource->getLabel();
    }

    public function writerClass(): ?string
    {
        return $this->resource->getWriterClass();
    }

    public function writerConfig(): array
    {
        return $this->resource->getWriterConfig() ?: [];
    }

    public function writer(): ?\BulkExport\Writer\WriterInterface
    {
        if ($this->writer) {
            return $this->writer;
        }

        $writerClass = $this->writerClass();
        $manager = $this->getWriterManager();
        if ($manager->has($writerClass)) {
            $this->writer = $manager->get($writerClass);
            if ($this->writer instanceof Configurable) {
                $config = $this->writerConfig();
                $this->writer->setConfig($config);
            }
        }

        return $this->writer;
    }

    protected function getWriterManager(): WriterManager
    {
        if (!$this->writerManager) {
            $this->writerManager = $this->getServiceLocator()->get(WriterManager::class);
        }
        return $this->writerManager;
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/bulk-export/id',
            [
                'controller' => $this->getControllerName(),
                'action' => $action,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }

    public function getResource(): \BulkExport\Entity\Exporter
    {
        return $this->resource;
    }
}
