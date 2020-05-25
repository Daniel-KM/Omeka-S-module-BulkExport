<?php
namespace BulkExport\Formatter;

use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Log\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Ods extends AbstractSpreadsheetFormatter
{
    protected $label = 'ods';
    protected $extension = 'ods';
    protected $responseHeaders = [
        'Content-type' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];
    protected $spreadsheetType = Type::ODS;

    public function format($resources, $output = null, array $options = [])
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'To process export to "{format}", the php extensions "zip" and "xml" are required.', // @translate
                ['format' => $this->getLabel()]
            ));
            $this->hasError = false;
            $resources = false;
        }
        return parent::format($resources, $output, $options);
    }

    protected function process()
    {
        $tempDir = $this->services->get('Config')['temp_dir'] ?: sys_get_temp_dir();
        $filepath = $this->isOutput
            ? $this->output
            // TODO Use Omeka factory for temp files.
            // TODO Use the method openToBrowser() too.
            // "php://temp" doesn't seem to work.
            : tempnam($tempDir, 'omk_export_');

        $writer = WriterFactory::create($this->spreadsheetType);
        try {
            $writer
                ->setTempFolder($tempDir)
                ->openToFile($filepath);
        } catch (\Box\Spout\Common\Exception\IOException $e) {
            $this->hasError = true;
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'Unable to open output: {error}.', // @translate
                ['error' => error_get_last()['message']]
            ));
        }
        if ($this->hasError) {
            return;
        }

        // TODO Add a check for the separator in the values.

        // First loop to get all headers.
        $rowHeaders = $this->prepareHeaders();
        $writer
            ->addRow(array_keys($rowHeaders));

        $outputRowForResource = function (AbstractResourceEntityRepresentation $resource) use ($rowHeaders, $writer) {
            $row = $this->prepareRow($resource, $rowHeaders);
            // Do a diff to avoid issue if a resource was update during process.
            // Order the row according to headers, keeping empty values.
            $row = array_values(array_replace($rowHeaders, array_intersect_key($row, $rowHeaders)));
            $writer
                ->addRow($row);
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

        $writer
            ->close();

        if (!$this->isOutput) {
            $this->content = file_get_contents($filepath);
            unlink($filepath);
        }
    }
}
