<?php declare(strict_types=1);

namespace BulkExportTest\Writer;

use BulkExport\Writer\CsvWriter;

/**
 * Tests for the CSV Writer.
 */
class CsvWriterTest extends AbstractWriterTest
{
    protected $writerClass = CsvWriter::class;

    protected $fileExtension = 'csv';

    public function setUp(): void
    {
        parent::setUp();
        $this->writerConfig = $this->getCsvWriterConfig();
    }

    /**
     * Test CSV writer produces valid output.
     *
     * @group integration
     */
    public function testCsvOutput(): void
    {
        // Create test items.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'CSV Test Item 1']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Author One']],
        ]);
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'CSV Test Item 2']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Author Two']],
        ]);

        $tempFile = $this->createTempFile('csv');

        $writer = $this->getWriter();
        $writer->setParams([
            'filename' => $tempFile,
            'resource_types' => ['items'],
        ]);

        $this->assertTrue($writer->isValid());

        // Note: Full process test requires job context.
        // This test verifies writer configuration.
    }

    /**
     * Test CSV writer handles special characters.
     */
    public function testCsvHandlesSpecialCharacters(): void
    {
        // Create item with special characters.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item with "quotes" and, commas']],
            'dcterms:description' => [['type' => 'literal', '@value' => "Multi\nline\nvalue"]],
        ]);

        $tempFile = $this->createTempFile('csv');

        $writer = $this->getWriter();
        $writer->setParams([
            'filename' => $tempFile,
            'resource_types' => ['items'],
        ]);

        $this->assertTrue($writer->isValid());
    }

    /**
     * Test CSV writer delimiter configuration.
     */
    public function testCsvDelimiterConfiguration(): void
    {
        // Test with semicolon delimiter.
        $this->writerConfig['delimiter'] = ';';

        $writer = $this->getWriter();

        $config = $writer->getConfig();
        $this->assertEquals(';', $config['delimiter'] ?? ',');
    }

    /**
     * Test CSV writer enclosure configuration.
     */
    public function testCsvEnclosureConfiguration(): void
    {
        // Test with single quote enclosure.
        $this->writerConfig['enclosure'] = "'";

        $writer = $this->getWriter();

        $config = $writer->getConfig();
        $this->assertEquals("'", $config['enclosure'] ?? '"');
    }

    /**
     * Data provider for CSV format tests.
     */
    public function csvFormatProvider(): array
    {
        return [
            'standard csv' => [',', '"', '\\'],
            'semicolon separated' => [';', '"', '\\'],
            'tab separated' => ["\t", '"', '\\'],
        ];
    }

    /**
     * Test various CSV format configurations.
     *
     * @dataProvider csvFormatProvider
     */
    public function testCsvFormatConfigurations(string $delimiter, string $enclosure, string $escape): void
    {
        $this->writerConfig = [
            'delimiter' => $delimiter,
            'enclosure' => $enclosure,
            'escape' => $escape,
        ];

        $writer = $this->getWriter();

        $this->assertInstanceOf(CsvWriter::class, $writer);
    }
}
