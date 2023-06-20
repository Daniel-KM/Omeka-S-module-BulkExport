<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ResourceFieldsTrait;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractFieldsFormatter extends AbstractFormatter
{
    use ListTermsTrait;
    use MetadataToStringTrait;
    use ResourceFieldsTrait;

    protected $defaultOptionsFields = [
        'format_fields' => 'name',
        'format_generic' => 'raw',
        'format_resource' => 'url_title',
        'format_resource_property' => 'dcterms:identifier',
        'format_uri' => 'uri_label',
        'only_first' => false,
        'empty_fields' => false,
    ];

    /**
     * Useful only for spreadsheet header.
     *
     * @var bool
     */
    protected $prependFieldNames = false;

    public function format($resources, $output = null, array $options = []): self
    {
        return parent::format($resources, $output, $options + $this->defaultOptionsFields);
    }

    protected function process(): self
    {
        $this
            ->prepareFieldNames($this->options['metadata'], $this->options['metadata_exclude']);

        if (!count($this->fieldNames)) {
            $this->logger->warn('No metadata are used in any resources.'); // @translate
            return $this;
        }

        $this->initializeOutput();
        if ($this->hasError) {
            return $this;
        }

        if ($this->prependFieldNames) {
            $formatFields = $this->options['format_fields'] ?? 'name';
            $this->labelFormatFields = $formatFields;
            if ($formatFields === 'label' || $formatFields === 'template') {
                $this
                    ->prepareFieldLabels($formatFields === 'template')
                    ->writeFields($this->fieldLabels);
            } else {
                $this
                    ->writeFields($this->fieldNames);
            }
        }

        if ($this->isId) {
            foreach ($this->resourceIds as $resourceId) {
                try {
                    /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (NotFoundException $e) {
                    continue;
                }
                $dataResource = $this->getDataResource($resource);
                if (count($dataResource)) {
                    $this
                        ->writeFields($dataResource);
                }
            }
        } else {
            foreach ($this->resources as $resource) {
                $dataResource = $this->getDataResource($resource);
                if (count($dataResource)) {
                    $this
                        ->writeFields($dataResource);
                }
            }
        }

        $this->finalizeOutput();
        return $this;
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): array
    {
        $dataResource = [];
        $removeEmptyFields = !$this->options['empty_fields'];
        foreach ($this->fieldNames as $fieldName) {
            $values = $this->stringMetadata($resource, $fieldName);
            if ($removeEmptyFields) {
                $values = array_filter($values, 'strlen');
                if (!count($values)) {
                    continue;
                }
            }
            if (isset($dataResource[$fieldName])) {
                $dataResource[$fieldName] = is_array($dataResource[$fieldName])
                    ? array_merge($dataResource[$fieldName], $values)
                    : array_merge([$dataResource[$fieldName]], $values);
            } else {
                $dataResource[$fieldName] = $values;
            }
        }
        return $dataResource;
    }

    /**
     * @param array $fields If fields contains arrays, this method should manage
     * them.
     */
    abstract protected function writeFields(array $fields): self;
}
