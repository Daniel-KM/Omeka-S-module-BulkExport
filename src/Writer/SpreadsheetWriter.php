<?php
namespace BulkExport\Writer;

use BulkExport\Form\Writer\CsvWriterConfigForm;
use BulkExport\Form\Writer\SpreadsheetWriterParamsForm;

class SpreadsheetWriter extends AbstractGenericWriter
{
    protected $label = 'Spreadsheet'; // @translate
    protected $mediaType = [
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv',
        'text/tab-separated-values',
    ];
    protected $configFormClass = CsvWriterConfigForm::class;
    protected $paramsFormClass = SpreadsheetWriterParamsForm::class;

    protected $configKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
    ];

    protected $paramsKeys = [
        'filename',
        'delimiter',
        'enclosure',
        'escape',
        'separator',
    ];

    protected $mediaTypeWriters = [
        'application/vnd.oasis.opendocument.spreadsheet' => OpenDocumentSpreadsheetWriter::class,
        'text/csv' => CsvWriter::class,
        'text/tab-separated-values' => TsvWriter::class,
    ];
}
