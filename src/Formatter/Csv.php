<?php declare(strict_types=1);

namespace BulkExport\Formatter;

class Csv extends AbstractSpreadsheetFormatter
{
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

    protected function initializeOutput(): self
    {
        parent::initializeOutput();
        // Prepend the utf-8 bom.
        if (!$this->hasError) {
            fwrite($this->handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        }
        return $this;
    }

    protected function writeFields(array $fields): self
    {
        fputcsv($this->handle, $fields, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
        return $this;
    }
}
