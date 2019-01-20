<?php
namespace BulkExportTest\Writer;

use BulkExport\Writer\CsvWriter;

if (!class_exists('BulkExportTest\Writer\AbstractWriter')) {
    require __DIR__ . '/AbstractWriter.php';
}

class CsvWriterTest extends AbstractWriter
{
    protected $WriterClass = CsvWriter::class;

    public function WriterProvider()
    {
        return [
            // filepath, options, expected for each test.
            ['test.csv', [], [true, 4, ['title', 'creator', 'description', 'tags', 'file']]],
            ['test_automap_columns.csv', [], [true, 4, [
                'Identifier', 'Dublin Core:Title', 'dcterms:creator', 'Description', 'Date', 'Publisher',
                'Collections', 'Tags', 'Resource template', 'Resource class',
                'Media url',
            ]]],
            ['test_cyrillic.csv', [], [false, 2, ['Dublin Core:Identifier', 'Collection', 'Dublin Core:Title', 'Dublin Core:Creator', 'Dublin Core:Date']]],
            ['empty.csv', [], [false, 1, []]],
            ['empty_really.csv', [], [false, 0, null]],
            ['test_column_missing.csv', [], [false, 4, ['Identifier', 'Title', 'Description']]],
            ['test_column_in_excess.csv', [], [false, 5, ['Identifier', 'Title', 'Description']]],
        ];
    }
}
