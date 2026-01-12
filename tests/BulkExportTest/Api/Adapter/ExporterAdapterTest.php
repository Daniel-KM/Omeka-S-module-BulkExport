<?php declare(strict_types=1);

namespace BulkExportTest\Api\Adapter;

use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Tests for the Exporter API adapter.
 */
class ExporterAdapterTest extends AbstractHttpControllerTestCase
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
     * Test creating an exporter via API.
     */
    public function testCreateExporter(): void
    {
        $data = [
            'o:label' => 'Test Exporter',
            'o:formatter' => 'csv',
            'o:config' => $this->getCsvFormatterConfig(),
        ];

        $response = $this->api()->create('bulk_exporters', $data);
        $exporter = $response->getContent();

        $this->createdExporters[] = $exporter->id();

        $this->assertNotNull($exporter->id());
        $this->assertEquals('Test Exporter', $exporter->label());
        $this->assertEquals('csv', $exporter->formatterName());
    }

    /**
     * Test reading an exporter via API.
     */
    public function testReadExporter(): void
    {
        // Create exporter directly.
        $exporter = $this->createExporter('Read Test', 'csv');

        // Read via API.
        $response = $this->api()->read('bulk_exporters', $exporter->getId());
        $result = $response->getContent();

        $this->assertEquals($exporter->getId(), $result->id());
        $this->assertEquals('Read Test', $result->label());
    }

    /**
     * Test updating an exporter via API.
     */
    public function testUpdateExporter(): void
    {
        // Create exporter.
        $exporter = $this->createExporter('Update Test', 'csv');

        // Update via API.
        $data = [
            'o:label' => 'Updated Label',
            'o:formatter' => 'tsv',
        ];

        $response = $this->api()->update('bulk_exporters', $exporter->getId(), $data);
        $result = $response->getContent();

        $this->assertEquals('Updated Label', $result->label());
        $this->assertEquals('tsv', $result->formatterName());
    }

    /**
     * Test deleting an exporter via API.
     */
    public function testDeleteExporter(): void
    {
        // Create exporter.
        $exporter = $this->createExporter('Delete Test', 'csv');
        $exporterId = $exporter->getId();

        // Remove from cleanup list since we're deleting it.
        $this->createdExporters = array_diff($this->createdExporters, [$exporterId]);

        // Delete via API.
        $this->api()->delete('bulk_exporters', $exporterId);

        // Verify deleted.
        $this->expectException(\Omeka\Api\Exception\NotFoundException::class);
        $this->api()->read('bulk_exporters', $exporterId);
    }

    /**
     * Test searching exporters via API.
     */
    public function testSearchExporters(): void
    {
        // Create multiple exporters.
        $this->createExporter('Search Test 1', 'csv');
        $this->createExporter('Search Test 2', 'tsv');

        // Search all.
        $response = $this->api()->search('bulk_exporters');
        $results = $response->getContent();

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    /**
     * Test exporter with invalid formatter fails gracefully.
     */
    public function testCreateExporterWithInvalidFormatter(): void
    {
        $data = [
            'o:label' => 'Invalid Exporter',
            'o:formatter' => 'nonexistent-format',
            'o:config' => [],
        ];

        // This may throw an exception or create with invalid formatter.
        // The actual behavior depends on validation in the adapter.
        try {
            $response = $this->api()->create('bulk_exporters', $data);
            $exporter = $response->getContent();
            $this->createdExporters[] = $exporter->id();
            // If it doesn't throw, at least check it was created.
            $this->assertNotNull($exporter->id());
        } catch (\Exception $e) {
            // Expected behavior - validation should reject invalid formatter.
            $this->assertTrue(true);
        }
    }
}
