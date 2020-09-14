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

    protected function writeFields(array $fields)
    {
        fputcsv($this->handle, $fields, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
    }
}
