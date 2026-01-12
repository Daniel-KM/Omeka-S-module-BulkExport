<?php declare(strict_types=1);

namespace BulkExportTest\Job;

use BulkExport\Entity\Export;
use BulkExport\Job\Export as ExportJob;
use BulkExport\Writer\CsvWriter;
use BulkExport\Writer\TsvWriter;
use Omeka\Entity\Job;
use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Integration tests for format_fields_labels feature.
 *
 * Tests verify that:
 * - Custom labels appear in export headers
 * - Multiple fields can be merged into one column
 * - Fields are ordered according to format_fields_labels
 *
 * @group integration
 */
class FormatFieldsLabelsTest extends AbstractHttpControllerTestCase
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
     * Test that custom labels appear in CSV export headers.
     */
    public function testCustomLabelsInCsvHeaders(): void
    {
        // Create test item with creator and contributor.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Author A']],
            'dcterms:contributor' => [['type' => 'literal', '@value' => 'Contributor B']],
        ]);

        // Create exporter with format_fields_labels configuration.
        $exporter = $this->createExporter('CSV Labels Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:creator', 'dcterms:contributor'],
                'format_fields' => 'name',
                'format_fields_labels' => [
                    'Person = dcterms:creator dcterms:contributor',
                ],
                'separator' => ' | ',
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Get exported file path.
        $filepath = $this->getExportFilePath($export->getId());
        $this->assertNotNull($filepath, 'Export file path should not be null');
        $this->assertFileExists($filepath, 'Export file should exist');

        // Parse CSV and check headers.
        $rows = $this->parseCsvFile($filepath);
        $this->assertNotEmpty($rows, 'CSV should have rows');

        $headers = $rows[0];

        // "Person" should be in headers (merged from dcterms:creator and dcterms:contributor).
        $this->assertContains('Person', $headers, 'Custom label "Person" should be in headers');

        // dcterms:creator and dcterms:contributor should NOT be in headers (merged into Person).
        $this->assertNotContains('dcterms:creator', $headers, 'dcterms:creator should not be in headers (merged)');
        $this->assertNotContains('dcterms:contributor', $headers, 'dcterms:contributor should not be in headers (merged)');
    }

    /**
     * Test that values from multiple fields are merged into one column.
     */
    public function testMergedFieldValues(): void
    {
        // Create test item with creator and contributor.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Merged Values Test']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Creator Value']],
            'dcterms:contributor' => [['type' => 'literal', '@value' => 'Contributor Value']],
        ]);

        // Create exporter with format_fields_labels configuration.
        $exporter = $this->createExporter('CSV Merge Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:creator', 'dcterms:contributor'],
                'format_fields' => 'name',
                'format_fields_labels' => [
                    'Person = dcterms:creator dcterms:contributor',
                ],
                'separator' => ' | ',
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Get exported file path.
        $filepath = $this->getExportFilePath($export->getId());
        $this->assertNotNull($filepath);
        $this->assertFileExists($filepath);

        // Parse CSV.
        $rows = $this->parseCsvFile($filepath);
        $this->assertGreaterThan(1, count($rows), 'CSV should have header and data rows');

        $headers = $rows[0];
        $personIndex = array_search('Person', $headers);
        $this->assertNotFalse($personIndex, '"Person" column should exist');

        // Check data row.
        $dataRow = $rows[1];
        $personValue = $dataRow[$personIndex] ?? '';

        // Both values should be present in the merged column.
        $this->assertStringContainsString('Creator Value', $personValue, 'Person column should contain creator value');
        $this->assertStringContainsString('Contributor Value', $personValue, 'Person column should contain contributor value');
    }

    /**
     * Test that fields are ordered according to format_fields_labels.
     */
    public function testFieldsOrdering(): void
    {
        // Create test item.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Order Test']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Creator']],
            'dcterms:subject' => [['type' => 'literal', '@value' => 'Subject']],
            'dcterms:description' => [['type' => 'literal', '@value' => 'Description']],
        ]);

        // Create exporter with specific ordering.
        $exporter = $this->createExporter('CSV Order Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:creator', 'dcterms:subject', 'dcterms:description'],
                'format_fields' => 'name',
                'format_fields_labels' => [
                    'Subject = dcterms:subject',
                    'Author = dcterms:creator',
                ],
                'separator' => ' | ',
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Get exported file path.
        $filepath = $this->getExportFilePath($export->getId());
        $this->assertNotNull($filepath);
        $this->assertFileExists($filepath);

        // Parse CSV.
        $rows = $this->parseCsvFile($filepath);
        $headers = $rows[0];

        // "Subject" should come before "Author" (as specified in format_fields_labels).
        $subjectIndex = array_search('Subject', $headers);
        $authorIndex = array_search('Author', $headers);

        $this->assertNotFalse($subjectIndex, '"Subject" should be in headers');
        $this->assertNotFalse($authorIndex, '"Author" should be in headers');
        $this->assertLessThan($authorIndex, $subjectIndex, '"Subject" should come before "Author" in headers');
    }

    /**
     * Test multiple merged fields with different labels.
     */
    public function testMultipleMergedFields(): void
    {
        // Create test item with multiple metadata fields.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Multiple Merge Test']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Creator A']],
            'dcterms:contributor' => [['type' => 'literal', '@value' => 'Contributor B']],
            'dcterms:subject' => [['type' => 'literal', '@value' => 'Subject X']],
            'dcterms:temporal' => [['type' => 'literal', '@value' => 'Temporal Y']],
        ]);

        // Create exporter with multiple merges.
        $exporter = $this->createExporter('CSV Multi Merge Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:creator', 'dcterms:contributor', 'dcterms:subject', 'dcterms:temporal'],
                'format_fields' => 'name',
                'format_fields_labels' => [
                    'Person = dcterms:creator dcterms:contributor',
                    'Topic = dcterms:subject dcterms:temporal',
                ],
                'separator' => ' | ',
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Get exported file path.
        $filepath = $this->getExportFilePath($export->getId());
        $this->assertNotNull($filepath);
        $this->assertFileExists($filepath);

        // Parse CSV.
        $rows = $this->parseCsvFile($filepath);
        $headers = $rows[0];

        // Both merged labels should be present.
        $this->assertContains('Person', $headers, '"Person" should be in headers');
        $this->assertContains('Topic', $headers, '"Topic" should be in headers');

        // Original field names should not be present.
        $this->assertNotContains('dcterms:creator', $headers);
        $this->assertNotContains('dcterms:contributor', $headers);
        $this->assertNotContains('dcterms:subject', $headers);
        $this->assertNotContains('dcterms:temporal', $headers);

        // Check that data is merged correctly.
        $dataRow = $rows[1];
        $personIndex = array_search('Person', $headers);
        $topicIndex = array_search('Topic', $headers);

        $personValue = $dataRow[$personIndex] ?? '';
        $topicValue = $dataRow[$topicIndex] ?? '';

        $this->assertStringContainsString('Creator A', $personValue);
        $this->assertStringContainsString('Contributor B', $personValue);
        $this->assertStringContainsString('Subject X', $topicValue);
        $this->assertStringContainsString('Temporal Y', $topicValue);
    }

    /**
     * Test TSV export with format_fields_labels.
     */
    public function testTsvExportWithLabels(): void
    {
        // Create test item.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'TSV Labels Test']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'TSV Creator']],
            'dcterms:contributor' => [['type' => 'literal', '@value' => 'TSV Contributor']],
        ]);

        // Create TSV exporter with format_fields_labels.
        $exporter = $this->createExporter('TSV Labels Test', TsvWriter::class, $this->getTsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:creator', 'dcterms:contributor'],
                'format_fields' => 'name',
                'format_fields_labels' => [
                    'Person = dcterms:creator dcterms:contributor',
                ],
                'separator' => ' | ',
                // TSV-specific settings (normally set by handleParamsForm).
                'delimiter' => "\t",
                'enclosure' => '"',
                'escape' => '\\',
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Get exported file path.
        $filepath = $this->getExportFilePath($export->getId());
        $this->assertNotNull($filepath);
        $this->assertFileExists($filepath);

        // Parse TSV (tab-delimited).
        $rows = $this->parseCsvFile($filepath, "\t");
        $headers = $rows[0];

        $this->assertContains('Person', $headers, '"Person" should be in TSV headers');
    }

    /**
     * Test that unlisted fields are appended after format_fields_labels.
     */
    public function testUnlistedFieldsAppended(): void
    {
        // Create test item with multiple fields.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Unlisted Test']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Creator']],
            'dcterms:description' => [['type' => 'literal', '@value' => 'Description']],
        ]);

        // Create exporter with only one field in format_fields_labels.
        $exporter = $this->createExporter('CSV Unlisted Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:creator', 'dcterms:description'],
                'format_fields' => 'name',
                'format_fields_labels' => [
                    'Author = dcterms:creator',
                ],
                'separator' => ' | ',
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Get exported file path.
        $filepath = $this->getExportFilePath($export->getId());
        $this->assertNotNull($filepath);
        $this->assertFileExists($filepath);

        // Parse CSV.
        $rows = $this->parseCsvFile($filepath);
        $headers = $rows[0];

        // "Author" should be first (from format_fields_labels).
        $authorIndex = array_search('Author', $headers);
        $this->assertNotFalse($authorIndex, '"Author" should be in headers');
        $this->assertEquals(0, $authorIndex, '"Author" should be first in headers');

        // Unlisted fields should still be present.
        $this->assertContains('o:id', $headers, 'o:id should still be in headers');
        $this->assertContains('dcterms:title', $headers, 'dcterms:title should still be in headers');
        $this->assertContains('dcterms:description', $headers, 'dcterms:description should still be in headers');
    }

    /**
     * Get the export file path after job completion.
     *
     * @param int $exportId Export ID.
     * @return string|null File path or null.
     */
    protected function getExportFilePath(int $exportId): ?string
    {
        // Refresh export from database to get updated filename.
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $exportEntity = $entityManager->find(Export::class, $exportId);
        if (!$exportEntity) {
            return null;
        }

        $filename = $exportEntity->getFilename();
        if (!$filename) {
            return null;
        }

        // Build absolute path.
        if (mb_substr($filename, 0, 1) === '/') {
            return $filename;
        }

        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return $basePath . '/bulk_export/' . $filename;
    }
}
