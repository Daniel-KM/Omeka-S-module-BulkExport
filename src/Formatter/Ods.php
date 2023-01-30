<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Log\Stdlib\PsrMessage;
use OpenSpout\Common\Type;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

class Ods extends AbstractSpreadsheetFormatter
{
    protected $label = 'ods';
    protected $extension = 'ods';
    protected $responseHeaders = [
        'Content-type' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];
    protected $spreadsheetType = Type::ODS;

    public function format($resources, $output = null, array $options = []): FormatterInterface
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'To process export to "{format}", the php extensions "zip" and "xml" are required.', // @translate
                ['format' => $this->getLabel()]
            ));
            $this->hasError = true;
            return $this;
        }

        $tempDir = $this->services->get('Config')['temp_dir'] ?: sys_get_temp_dir();
        if (!$this->createDir($tempDir)) {
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'The temporary folder "{folder}" does not exist or is not writeable.', // @translate
                ['folder' => $tempDir]
            ));
            $this->hasError = true;
            return $this;
        }

        return parent::format($resources, $output, $options);
    }

    protected function initializeOutput(): FormatterInterface
    {
        $tempDir = $this->services->get('Config')['temp_dir'] ?: sys_get_temp_dir();
        $this->filepath = $this->isOutput
            ? $this->output
            // TODO Use Omeka factory for temp files.
            // TODO Use the method openToBrowser() too.
            // "php://temp" doesn't seem to work.
            : @tempnam($tempDir, 'omk_bke_');

        $this->spreadsheetWriter = WriterEntityFactory::createODSWriter();
        try {
            $this->spreadsheetWriter
                ->setTempFolder($tempDir)
                ->openToFile($this->filepath);
        } catch (\OpenSpout\Common\Exception\IOException $e) {
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
        $row = WriterEntityFactory::createRowFromArray($fields);
        $this->spreadsheetWriter
            ->addRow($row);
        return $this;
    }

    protected function finalizeOutput(): FormatterInterface
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
