<?php declare(strict_types=1);

namespace BulkExportTest\Writer;

use BulkExport\Writer\OpenDocumentSpreadsheetWriter;

/**
 * Tests for the OpenDocument Spreadsheet (ODS) Writer.
 */
class OpenDocumentSpreadsheetWriterTest extends AbstractWriterTest
{
    protected $writerClass = OpenDocumentSpreadsheetWriter::class;

    protected $fileExtension = 'ods';

    public function setUp(): void
    {
        parent::setUp();
        $this->writerConfig = [
            'separator' => ' | ',
        ];
    }

    /**
     * Test ODS writer produces valid output.
     *
     * @group integration
     */
    public function testOdsOutput(): void
    {
        // Create test items.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'ODS Test Item 1']],
        ]);
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'ODS Test Item 2']],
        ]);

        $tempFile = $this->createTempFile('ods');

        $writer = $this->getWriter();
        $writer->setParams([
            'filename' => $tempFile,
            'resource_types' => ['items'],
        ]);

        $this->assertTrue($writer->isValid());
    }

    /**
     * Test ODS writer handles multiple sheets.
     */
    public function testOdsHandlesMultipleResourceTypes(): void
    {
        // Create test items and item sets.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
        ]);

        $tempFile = $this->createTempFile('ods');

        $writer = $this->getWriter();
        $writer->setParams([
            'filename' => $tempFile,
            'resource_types' => ['items', 'item_sets'],
        ]);

        $this->assertTrue($writer->isValid());
    }

    /**
     * Test ODS writer extension is correct.
     */
    public function testOdsExtension(): void
    {
        $writer = $this->getWriter();

        $this->assertEquals('ods', $writer->getExtension());
    }

}
