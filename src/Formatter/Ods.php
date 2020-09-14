<?php

namespace BulkExport\Formatter;

use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Log\Stdlib\PsrMessage;

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

    protected function initializeOutput()
    {
        $tempDir = $this->services->get('Config')['temp_dir'] ?: sys_get_temp_dir();
        $this->filepath = $this->isOutput
            ? $this->output
            // TODO Use Omeka factory for temp files.
            // TODO Use the method openToBrowser() too.
            // "php://temp" doesn't seem to work.
            : tempnam($tempDir, 'omk_export_');

        $this->spreadsheetWriter = WriterFactory::create($this->spreadsheetType);
        try {
            $this->spreadsheetWriter
                ->setTempFolder($tempDir)
                ->openToFile($this->filepath);
        } catch (\Box\Spout\Common\Exception\IOException $e) {
            $this->hasError = true;
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'Unable to open output: {error}.', // @translate
                ['error' => error_get_last()['message']]
            ));
        }
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
        $this->spreadsheetWriter
            ->close();
        if (!$this->isOutput) {
            $this->content = file_get_contents($this->filepath);
            unlink($this->filepath);
        }
        return $this;
    }
}
