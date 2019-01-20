<?php
namespace BulkExport\Reader;

use BulkExport\Form\Reader\CsvReaderConfigForm;
use BulkExport\Form\Reader\SpreadsheetReaderParamsForm;

class SpreadsheetReader extends AbstractGenericReader
{
    protected $label = 'Spreadsheet'; // @translate
    protected $mediaType = [
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv',
        'text/tab-separated-values',
    ];
    protected $configFormClass = CsvReaderConfigForm::class;
    protected $paramsFormClass = SpreadsheetReaderParamsForm::class;

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

    protected $mediaTypeReaders = [
        'application/vnd.oasis.opendocument.spreadsheet' => OpenDocumentSpreadsheetReader::class,
        'text/csv' => CsvReader::class,
        'text/tab-separated-values' => TsvReader::class,
    ];
}
