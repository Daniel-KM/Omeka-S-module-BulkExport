<?php declare(strict_types=1);

namespace BulkExport\Formatter;

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

    public function format($resources, $output = null, array $options = []): self
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->logger->err(
                'To process export to "{format}", the php extensions "zip" and "xml" are required.', // @translate
                ['format' => $this->getLabel()]
            );
            $this->hasError = true;
            return $this;
        }

        $tempDir = $this->services->get('Config')['temp_dir'] ?: sys_get_temp_dir();
        if (!$this->createDir($tempDir)) {
            $this->logger->err(
                'The temporary folder "{folder}" does not exist or is not writeable.', // @translate
                ['folder' => $tempDir]
            );
            $this->hasError = true;
            return $this;
        }

        return parent::format($resources, $output, $options);
    }

    protected function initializeOutput(): self
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
            $this->logger->err(
                'Unable to open output: {error}.', // @translate
                ['error' => error_get_last()['message']]
            );
        }
        return $this;
    }

    protected function writeFields(array $fields): self
    {
        $row = WriterEntityFactory::createRowFromArray($fields);
        $this->spreadsheetWriter
            ->addRow($row);
        return $this;
    }

    protected function finalizeOutput(): self
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
