<?php
namespace BulkExport\Formatter;

use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Csv extends AbstractSpreadsheetFormatter
{
    use ListTermsTrait;
    use MetadataToStringTrait;

    protected $label = 'csv';
    protected $extension = 'csv';
    protected $responseHeaders = [
        'Content-type' => 'text/csv',
    ];
    protected $defaultOptions = [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
    ];

    protected function process()
    {
        $this->initializeOutput();
        if ($this->hasError) {
            return;
        }

        // TODO Add a check for the separator in the values.

        // First loop to get all headers.
        $rowHeaders = $this->prepareHeaders();

        fputcsv($this->handle, array_keys($rowHeaders), $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);

        $outputRowForResource = function (AbstractResourceEntityRepresentation $resource) use ($rowHeaders) {
            $row = $this->prepareRow($resource, $rowHeaders);
            // Do a diff to avoid issue if a resource was update during process.
            // Order the row according to headers, keeping empty values.
            $row = array_values(array_replace($rowHeaders, array_intersect_key($row, $rowHeaders)));
            fputcsv($this->handle, $row, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
        };

        // Second loop to fill each row.
        /* @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        if ($this->isId) {
            foreach ($this->resourceIds as $resourceId) {
                try {
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    continue;
                }
                $outputRowForResource($resource);
            }
        } else {
            array_walk($this->resources, $outputRowForResource);
        }

        $this->finalizeOutput();
    }
}
