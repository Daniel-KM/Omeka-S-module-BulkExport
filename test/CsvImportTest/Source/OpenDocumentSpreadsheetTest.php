<?php
namespace CSVImportTest\Source;

use CSVImport\Source\OpenDocumentSpreadsheet;

if (!class_exists('CSVImportTest\Source\AbstractSource')) {
    require __DIR__ . '/AbstractSource.php';
}

class OpenDocumentSpreadsheetTest extends AbstractSource
{
    protected $sourceClass = OpenDocumentSpreadsheet::class;

    public function sourceProvider()
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
     * @dataProvider sourceProvider
     */
    public function testCountRows($filepath, $options, $expected)
    {
        $this->markTestSkipped('TODO Count empty rows with spreadsheet.');
    }
}
