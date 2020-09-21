<?php

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

    protected function initializeOutput()
    {
        parent::initializeOutput();
        // Prepend the utf-8 bom.
        if (!$this->hasError) {
            fwrite($this->handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        }
        return $this;
    }

    protected function writeFields(array $fields)
    {
        fputcsv($this->handle, $fields, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
    }
}
