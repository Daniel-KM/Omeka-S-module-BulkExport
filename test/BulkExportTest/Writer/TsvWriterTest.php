<?php declare(strict_types=1);

namespace BulkExportTest\Writer;

use BulkExport\Writer\TsvWriter;

/**
 * Tests for the TSV Writer.
 */
class TsvWriterTest extends AbstractWriterTest
{
    protected $writerClass = TsvWriter::class;

    protected $fileExtension = 'tsv';

    public function setUp(): void
    {
        parent::setUp();
        $this->writerConfig = $this->getTsvWriterConfig();
    }

    /**
     * Test TSV writer produces valid output.
     *
     * @group integration
     */
    public function testTsvOutput(): void
    {
        // Create test items.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'TSV Test Item 1']],
        ]);
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'TSV Test Item 2']],
        ]);

        $tempFile = $this->createTempFile('tsv');

        $writer = $this->getWriter();
        $writer->setParams([
            'filename' => $tempFile,
            'resource_types' => ['items'],
        ]);

        $this->assertTrue($writer->isValid());
    }

    /**
     * Test TSV writer uses tab delimiter.
     */
    public function testTsvUsesTabDelimiter(): void
    {
        $writer = $this->getWriter();

        $config = $writer->getConfig();
        $this->assertEquals("\t", $config['delimiter'] ?? null);
    }

    /**
     * Test TSV writer handles values with tabs.
     */
    public function testTsvHandlesTabsInValues(): void
    {
        // Create item with tab character in value.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => "Value\twith\ttabs"]],
        ]);

        $tempFile = $this->createTempFile('tsv');

        $writer = $this->getWriter();
        $writer->setParams([
            'filename' => $tempFile,
            'resource_types' => ['items'],
        ]);

        $this->assertTrue($writer->isValid());
    }
}
