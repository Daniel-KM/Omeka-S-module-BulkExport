<?php
namespace BulkExportTest\Writer;

use BulkExport\Writer\TsvWriter;

if (!class_exists('BulkExportTest\Writer\AbstractWriter')) {
    require __DIR__ . '/AbstractWriter.php';
}

class TsvWriterTest extends AbstractWriter
{
    protected $WriterClass = TsvWriter::class;

    public function WriterProvider()
    {
        return [
            // filepath, options, expected for each test.
            ['test_column_missing.tsv', [], [false, 4, ['Identifier', 'Title', 'Description']]],
            ['test_column_in_excess.tsv', [], [false, 5, ['Identifier', 'Title', 'Description']]],
        ];
    }
}
