<?php declare(strict_types=1);

namespace BulkExport\Writer;

use Box\Spout\Writer\WriterFactory;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractSpreadsheetWriter extends AbstractFieldsWriter
{
    protected $configKeys = [
        'separator',
    ];

    protected $paramsKeys = [
        'separator',
    ];

    protected $spreadsheetOptions = [
        'separator' => ' | ',
        'has_separator' => true,
        'empty_fields' => true,
    ];

    protected $prependFieldNames = true;

    /**
     * @var \Box\Spout\Writer\WriterInterface
     */
    protected $spreadsheetWriter;

    /**
     * Type of spreadsheet (default to csv).
     *
     * @var \Box\Spout\Common\Type
     */
    protected $spreadsheetType;

    protected function initializeParams()
    {
        $this->options = $this->spreadsheetOptions + $this->options;
        $separator = $this->getParam('separator', '');
        $this->options['separator'] = $separator;
        $this->options['has_separator'] = mb_strlen($separator) > 0;
        $this->options['only_first'] = !$this->options['has_separator'];
        if ($this->options['only_first']) {
            $this->logger->warn(
                'No separator selected: only the first value of each property of each resource will be output.' // @translate
            );
        }
        return parent::initializeParams();
    }

    protected function initializeOutput()
    {
        $this->spreadsheetWriter = WriterFactory::create($this->spreadsheetType);
        $this->spreadsheetWriter
            ->openToFile($this->filepath);
        return $this;
    }

    protected function writeFields(array $fields)
    {
        $this->spreadsheetWriter
            ->addRow($fields);
        return $this;
    }

    protected function finalizeOutput()
    {
        $this->spreadsheetWriter->close();
        return $this;
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource)
    {
        $dataResource = [];

        if ($this->options['only_first']) {
            foreach ($this->fieldNames as $fieldName) {
                $values = $this->stringMetadata($resource, $fieldName);
                $dataResource[] = (string) reset($values);
            }
            return $dataResource;
        }

        $separator = $this->options['separator'];
        foreach ($this->fieldNames as $fieldName) {
            $values = $this->stringMetadata($resource, $fieldName);
            // Check if one of the values has the separator.
            $check = array_filter($values, function ($v) use ($separator) {
                return strpos((string) $v, $separator) !== false;
            });
            if ($check) {
                $this->logger->warn(
                    'Skipped {resource_type} #{resource_id}: it contains the separator "{separator}".', // @translate
                    ['resource_type' => $this->mapResourceTypeToText($resource->getJsonLdType()), 'resource_id' => $resource->id(), 'separator' => $separator]
                );
                $dataResource = [];
                break;
            }
            $dataResource[] = implode($separator, $values);
        }
        return $dataResource;
    }
}
