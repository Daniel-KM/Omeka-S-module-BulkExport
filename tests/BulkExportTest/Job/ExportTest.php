<?php declare(strict_types=1);

namespace BulkExportTest\Job;

use BulkExport\Job\Export;


use Omeka\Entity\Job;
use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Unit and functional tests for the Export job.
 */
class ExportTest extends AbstractHttpControllerTestCase
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
     * Test job fails with missing bulk_export_id argument.
     */
    public function testJobFailsWithMissingExportId(): void
    {
        $args = [];

        $job = $this->runJob(Export::class, $args, true);

        $this->assertEquals(Job::STATUS_ERROR, $job->getStatus());
    }

    /**
     * Test job fails with invalid bulk_export_id.
     */
    public function testJobFailsWithInvalidExportId(): void
    {
        $args = [
            'bulk_export_id' => 999999,
        ];

        $job = $this->runJob(Export::class, $args, true);

        $this->assertEquals(Job::STATUS_ERROR, $job->getStatus());
    }

    /**
     * Test basic CSV export completes successfully.
     *
     * @group integration
     */
    public function testCsvExportCompletes(): void
    {
        // Create test items.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Document 1']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Author A']],
        ]);
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Document 2']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Author B']],
        ]);

        // Create exporter and export.
        $exporter = $this->createExporter('CSV Test', 'csv', $this->getCsvFormatterConfig());
        $export = $this->createExport($exporter, [
            'resource_types' => ['items'],
        ]);

        $args = [
            'bulk_export_id' => $export->getId(),
        ];

        $job = $this->runJob(Export::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test TSV export completes successfully.
     *
     * @group integration
     */
    public function testTsvExportCompletes(): void
    {
        // Create test item.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'TSV Test Document']],
        ]);

        // Create exporter and export.
        $exporter = $this->createExporter('TSV Test', 'tsv', $this->getTsvFormatterConfig());
        $export = $this->createExport($exporter, [
            'resource_types' => ['items'],
        ]);

        $args = [
            'bulk_export_id' => $export->getId(),
        ];

        $job = $this->runJob(Export::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test export with query filter.
     *
     * @group integration
     */
    public function testExportWithQueryFilter(): void
    {
        // Create test items.
        $item1 = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Filtered Item']],
        ]);
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Other Item']],
        ]);

        // Create exporter with query filter for item1 only.
        $exporter = $this->createExporter('Filtered Export', 'csv', $this->getCsvFormatterConfig());
        $export = $this->createExport($exporter, [
            'resource_types' => ['items'],
            'query' => ['id' => [$item1->id()]],
        ]);

        $args = [
            'bulk_export_id' => $export->getId(),
        ];

        $job = $this->runJob(Export::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test export with empty result set.
     *
     * @group integration
     */
    public function testExportWithNoResults(): void
    {
        // Create exporter with query that matches nothing.
        $exporter = $this->createExporter('Empty Export', 'csv', $this->getCsvFormatterConfig());
        $export = $this->createExport($exporter, [
            'resource_types' => ['items'],
            'query' => ['id' => [999999]],
        ]);

        $args = [
            'bulk_export_id' => $export->getId(),
        ];

        $job = $this->runJob(Export::class, $args);

        // Job should complete even with no results.
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test that job is correctly associated with export after completion.
     *
     * This test verifies the fix for issue #13 (GitLab) where Doctrine would
     * throw a cascade persist exception when associating the job with the export.
     *
     * @group integration
     */
    public function testJobAssociatedWithExport(): void
    {
        // Create test item.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Job Association Test']],
        ]);

        // Create exporter and export.
        $exporter = $this->createExporter('CSV Test', 'csv', $this->getCsvFormatterConfig());
        $export = $this->createExport($exporter, [
            'formatter' => [
                'resource_types' => ['o:Item'],
            ],
        ]);

        $args = [
            'bulk_export_id' => $export->getId(),
        ];

        $job = $this->runJob(Export::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Refresh export from database to verify job association.
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $exportEntity = $entityManager->find(\BulkExport\Entity\Export::class, $export->getId());
        $this->assertNotNull($exportEntity, 'Export should exist in database');

        $associatedJob = $exportEntity->getJob();
        $this->assertNotNull($associatedJob, 'Export should have an associated job (fix for issue #13)');
        $this->assertEquals($job->getId(), $associatedJob->getId(), 'Associated job ID should match');
    }
}
