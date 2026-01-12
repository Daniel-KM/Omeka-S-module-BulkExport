<?php declare(strict_types=1);

namespace BulkExport\Api\Representation;

use BulkExport\Formatter\Manager as FormatterManager;
use BulkExport\Interfaces\Configurable;
use BulkExport\Writer\Manager as WriterManager;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class ExporterRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \BulkExport\Formatter\Manager
     */
    protected $formatterManager;

    /**
     * @var \BulkExport\Formatter\FormatterInterface
     */
    protected $formatter;

    /**
     * @var \BulkExport\Writer\Manager
     * @deprecated No more writer.
     */
    protected $writerManager;

    /**
     * @var \BulkExport\Writer\WriterInterface
     * @deprecated No more writer.
     */
    protected $writer;

    public function getControllerName()
    {
        return 'exporter';
    }

    public function getJsonLdType()
    {
        return 'o-bulk:Exporter';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();
        return [
            'o:id' => $this->id(),
            'o:owner' => $owner ? $owner->getReference()->jsonSerialize() : null,
            'o:label' => $this->label(),
            'o-bulk:formatter' => $this->formatterName(),
            'o-bulk:writer' => $this->writerClass(), // @deprecated
            'o:config' => $this->config(),
        ];
    }

    public function getResource(): \BulkExport\Entity\Exporter
    {
        return $this->resource;
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
        return $this->resource->getLabel();
    }

    public function config(): array
    {
        return $this->resource->getConfig();
    }

    public function configOption(string $part, $key)
    {
        $conf = $this->resource->getConfig();
        return $conf[$part][$key] ?? null;
    }

    /**
     * Get the formatter name (alias like 'csv', 'ods', etc.).
     */
    public function formatterName(): ?string
    {
        $formatter = $this->resource->getFormatter();
        if ($formatter) {
            return $formatter;
        }

        // Fallback: derive from writer class for backward compatibility.
        $writerClass = $this->resource->getWriter();
        if (!$writerClass) {
            return null;
        }

        return $this->writerClassToFormatterName($writerClass);
    }

    /**
     * Get the formatter instance.
     */
    public function formatter(): ?\BulkExport\Formatter\FormatterInterface
    {
        if ($this->formatter) {
            return $this->formatter;
        }

        $formatterName = $this->formatterName();
        if (!$formatterName) {
            return null;
        }

        $manager = $this->getFormatterManager();
        if (!$manager->has($formatterName)) {
            return null;
        }

        $this->formatter = $manager->get($formatterName);
        return $this->formatter;
    }

    /**
     * Get the formatter config (same as writer config for BC).
     */
    public function formatterConfig(): array
    {
        $conf = $this->config();
        return $conf['writer'] ?? [];
    }

    protected function getFormatterManager(): FormatterManager
    {
        if (!$this->formatterManager) {
            $this->formatterManager = $this->getServiceLocator()->get(FormatterManager::class);
        }
        return $this->formatterManager;
    }

    /**
     * Convert writer class name to formatter alias.
     */
    protected function writerClassToFormatterName(string $writerClass): ?string
    {
        $mapping = [
            'BulkExport\\Writer\\CsvWriter' => 'csv',
            'BulkExport\\Writer\\TsvWriter' => 'tsv',
            'BulkExport\\Writer\\TextWriter' => 'txt',
            'BulkExport\\Writer\\OpenDocumentSpreadsheetWriter' => 'ods',
            'BulkExport\\Writer\\OpenDocumentTextWriter' => 'odt',
            'BulkExport\\Writer\\JsonTableWriter' => 'json-table',
            'BulkExport\\Writer\\GeoJsonWriter' => 'geojson',
        ];
        return $mapping[$writerClass] ?? null;
    }

    /**
     * @deprecated No more writer. Use formatterName() instead.
     */
    public function writerClass(): ?string
    {
        return $this->resource->getWriter();
    }

    public function exporterConfig(): array
    {
        $conf = $this->config();
        return $conf['exporter'] ?? [];
    }

    public function writerConfig(): array
    {
        $conf = $this->config();
        return $conf['writer'] ?? [];
    }

    public function writer(): ?\BulkExport\Writer\WriterInterface
    {
        if ($this->writer) {
            return $this->writer;
        }

        $writerClass = $this->writerClass();
        $manager = $this->getWriterManager();
        if (!$manager->has($writerClass)) {
            return null;
        }

        $this->writer = $manager->get($writerClass);
        if ($this->writer instanceof Configurable) {
            $config = $this->writerConfig();
            $this->writer->setConfig($config);
        }

        $logger = $this->getServiceLocator()->get('Omeka\Logger');
        $this->writer->setLogger($logger);

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

    /**
     * Get the display title for this resource.
     *
     * @param string|null $default
     * @param array|string|null $lang
     * @return string|null
     *
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::displayTitle()
     */
    public function displayTitle($default = null, $lang = null)
    {
        $title = $this->label();
        if ($title === null || $title === '') {
            if ($default === null || $default === '') {
                $translator = $this->getServiceLocator()->get('MvcTranslator');
                $title = sprintf(
                    $translator->translate('Exporter #%d'), // @translate
                    $this->id()
                );
            } else {
                $title = $default;
            }
        }
        return $title;;
    }

    /**
     * Get a "pretty" link to this resource containing a thumbnail and
     * display title.
     *
     * @param string $thumbnailType Type of thumbnail to show
     * @param string|null $titleDefault See $default param for displayTitle()
     * @param string|null $action Action to link to (see link() and linkRaw())
     * @param array $attributes HTML attributes, key and value
     * @param array|string|null $lang Language IETF tag
     * @return string
     *
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::linkPretty()
     */
    public function linkPretty(
        $thumbnailType = 'square',
        $titleDefault = null,
        $action = null,
        array $attributes = null,
        $lang = null
    ) {
        $escape = $this->getViewHelper('escapeHtml');
        $thumbnail = $this->getViewHelper('thumbnail');
        $linkContent = sprintf(
            '%s<span class="resource-name">%s</span>',
            $thumbnail($this, $thumbnailType),
            $escape($this->displayTitle($titleDefault, $lang))
        );
        if (empty($attributes['class'])) {
            $attributes['class'] = 'resource-link';
        } else {
            $attributes['class'] .= ' resource-link';
        }
        return $this->linkRaw($linkContent, $action, $attributes);
    }
}
