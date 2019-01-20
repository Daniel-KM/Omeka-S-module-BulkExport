<?php
namespace BulkExportTest\Writer;

use BulkExport\Writer\OpenDocumentSpreadsheetWriter;

if (!class_exists('BulkExportTest\Writer\AbstractWriter')) {
    require __DIR__ . '/AbstractWriter.php';
}

class OpenDocumentSpreadsheetWriterTest extends AbstractWriter
{
    protected $WriterClass = OpenDocumentSpreadsheetWriter::class;

    public function WriterProvider()
    {
        return [
            // filepath, options, expected for each test.
            ['test_resources_heritage.ods', [], [true, 21, ['Identifier', 'Resource Type',
                'Collection Identifier', 'Item Identifier', 'Media Url', 'Resource class', 'Title',
                'Dublin Core : Creator', 'Date', 'Rights', 'Description', 'Dublin Core:Format',
                'Dublin Core : Spatial Coverage', 'Tags', 'Latitude', 'Longitude', 'Default Zoom',]]],
            ['test_column_missing.ods', [], [true, 4, ['Identifier', 'Title', 'Description']]],
            ['test_column_in_excess.ods', [], [false, 5, ['Identifier', 'Title', 'Description']]],
            ['test_column_in_excess_bis.ods', [], [true, 5, ['Identifier', 'Title', 'Description']]],
            ['test_column_in_excess_ter.ods', [], [true, 5, ['Identifier', 'Title', 'Description']]],
        ];
    }

    /**
     * @dataProvider WriterProvider
     */
    public function testCountRows($filepath, $options, $expected)
    {
        $this->markTestSkipped('TODO Count empty rows with spreadsheet.');
    }
}
