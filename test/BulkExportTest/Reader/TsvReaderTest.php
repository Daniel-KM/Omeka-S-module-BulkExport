<?php
namespace BulkExportTest\Reader;

use BulkExport\Reader\TsvReader;

if (!class_exists('BulkExportTest\Reader\AbstractReader')) {
    require __DIR__ . '/AbstractReader.php';
}

class TsvReaderTest extends AbstractReader
{
    protected $ReaderClass = TsvReader::class;

    public function ReaderProvider()
    {
        return [
            // filepath, options, expected for each test.
            ['test_column_missing.tsv', [], [false, 4, ['Identifier', 'Title', 'Description']]],
            ['test_column_in_excess.tsv', [], [false, 5, ['Identifier', 'Title', 'Description']]],
        ];
    }
}
