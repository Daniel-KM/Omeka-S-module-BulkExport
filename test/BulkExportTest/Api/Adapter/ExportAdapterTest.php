<?php declare(strict_types=1);

namespace BulkExportTest\Api\Adapter;

use BulkExport\Writer\CsvWriter;
use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Tests for the Export API adapter.
 */
class ExportAdapterTest extends AbstractHttpControllerTestCase
{
    use BulkExportTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test reading an export via API.
     */
    public function testReadExport(): void
    {
        // Create exporter and export.
        $exporter = $this->createExporter('Read Export Test', CsvWriter::class);
        $export = $this->createExport($exporter, ['resource_types' => ['items']]);

        // Read via API.
        $response = $this->api()->read('bulk_exports', $export->getId());
        $result = $response->getContent();

        $this->assertEquals($export->getId(), $result->id());
    }

    /**
     * Test deleting an export via API.
     */
    public function testDeleteExport(): void
    {
        // Create exporter and export with a dummy filename to avoid unlink error.
        $exporter = $this->createExporter('Delete Export Test', CsvWriter::class);
        $export = $this->createExport($exporter, []);

        // Set a non-existent filename to avoid directory unlink error.
        $export->setFilename('nonexistent_test_file.csv');
        $this->getEntityManager()->flush();

        $exportId = $export->getId();

        // Remove from cleanup list.
        $this->createdExports = array_diff($this->createdExports, [$exportId]);

        // Delete via API.
        $this->api()->delete('bulk_exports', $exportId);

        // Verify deleted.
        $this->expectException(\Omeka\Api\Exception\NotFoundException::class);
        $this->api()->read('bulk_exports', $exportId);
    }

    /**
     * Test searching exports via API.
     */
    public function testSearchExports(): void
    {
        // Create exporter and multiple exports.
        $exporter = $this->createExporter('Search Exports Test', CsvWriter::class);
        $this->createExport($exporter, []);
        $this->createExport($exporter, []);

        // Search all.
        $response = $this->api()->search('bulk_exports');
        $results = $response->getContent();

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    /**
     * Test search exports by exporter.
     */
    public function testSearchExportsByExporter(): void
    {
        // Create two exporters with exports.
        $exporter1 = $this->createExporter('Exporter 1', CsvWriter::class);
        $exporter2 = $this->createExporter('Exporter 2', CsvWriter::class);

        $this->createExport($exporter1, []);
        $this->createExport($exporter1, []);
        $this->createExport($exporter2, []);

        // Search by exporter 1.
        $response = $this->api()->search('bulk_exports', [
            'exporter_id' => $exporter1->getId(),
        ]);
        $results = $response->getContent();

        // Should find 2 exports for exporter 1.
        $this->assertCount(2, $results);
    }
}
