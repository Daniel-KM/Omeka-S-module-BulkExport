<?php declare(strict_types=1);

namespace BulkExportTest\Job;

use BulkExport\Entity\Export;
use BulkExport\Job\Export as ExportJob;
use BulkExport\Writer\CsvWriter;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Entity\Job;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for value_per_column export feature.
 */
class ValuePerColumnTest extends AbstractHttpControllerTestCase
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
     * Test basic value_per_column export with multiple values.
     */
    public function testValuePerColumnBasic(): void
    {
        // Create an item with multiple subjects.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Subject 1'],
                ['type' => 'literal', '@value' => 'Subject 2'],
                ['type' => 'literal', '@value' => 'Subject 3'],
            ],
        ]);

        $exporter = $this->createExporter('CSV Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:subject'],
                'value_per_column' => true,
                'separator' => ' | ',
                'query' => ['id' => $item->id()],
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $outputFile = $this->getExportFilePath($export->getId());
        $this->assertNotNull($outputFile);
        $this->assertFileExists($outputFile);

        $rows = $this->parseCsvFile($outputFile);
        $this->assertNotEmpty($rows);

        // Headers should have 3 dcterms:subject columns (one per value).
        $headers = $rows[0];
        $subjectCount = count(array_filter($headers, fn($h) => $h === 'dcterms:subject'));
        $this->assertEquals(3, $subjectCount, 'Should have 3 dcterms:subject columns');

        // Data row should have values in separate columns.
        $dataRow = $rows[1];
        $this->assertContains('Subject 1', $dataRow);
        $this->assertContains('Subject 2', $dataRow);
        $this->assertContains('Subject 3', $dataRow);
    }

    /**
     * Test value_per_column with items having different value counts.
     */
    public function testValuePerColumnDifferentCounts(): void
    {
        // Create items with different numbers of subjects.
        $item1 = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item 1']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Subject A'],
            ],
        ]);

        $item2 = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item 2']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Subject B'],
                ['type' => 'literal', '@value' => 'Subject C'],
                ['type' => 'literal', '@value' => 'Subject D'],
            ],
        ]);

        $exporter = $this->createExporter('CSV Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:subject'],
                'value_per_column' => true,
                'separator' => ' | ',
                'query' => ['id' => [$item1->id(), $item2->id()]],
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $outputFile = $this->getExportFilePath($export->getId());
        $this->assertNotNull($outputFile);
        $rows = $this->parseCsvFile($outputFile);

        // Headers should have 3 dcterms:subject columns (max from item2).
        $headers = $rows[0];
        $subjectCount = count(array_filter($headers, fn($h) => $h === 'dcterms:subject'));
        $this->assertEquals(3, $subjectCount, 'Should have 3 dcterms:subject columns (max count)');

        // Find subject column indices.
        $subjectIndices = [];
        foreach ($headers as $i => $h) {
            if ($h === 'dcterms:subject') {
                $subjectIndices[] = $i;
            }
        }

        // First item should have Subject A, then two empty columns.
        $dataRow1 = $rows[1];
        $this->assertEquals('Subject A', $dataRow1[$subjectIndices[0]]);
        $this->assertEquals('', $dataRow1[$subjectIndices[1]]);
        $this->assertEquals('', $dataRow1[$subjectIndices[2]]);

        // Second item should have all three subjects.
        $dataRow2 = $rows[2];
        $this->assertEquals('Subject B', $dataRow2[$subjectIndices[0]]);
        $this->assertEquals('Subject C', $dataRow2[$subjectIndices[1]]);
        $this->assertEquals('Subject D', $dataRow2[$subjectIndices[2]]);
    }

    /**
     * Test value_per_column with column_metadata for language.
     */
    public function testValuePerColumnWithLanguage(): void
    {
        // Create an item with subjects in different languages.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Sujet français', '@language' => 'fr'],
                ['type' => 'literal', '@value' => 'English subject', '@language' => 'en'],
                ['type' => 'literal', '@value' => 'Another French', '@language' => 'fr'],
            ],
        ]);

        $exporter = $this->createExporter('CSV Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:subject'],
                'value_per_column' => true,
                'column_metadata' => ['language'],
                'separator' => ' | ',
                'query' => ['id' => $item->id()],
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $outputFile = $this->getExportFilePath($export->getId());
        $this->assertNotNull($outputFile);
        $rows = $this->parseCsvFile($outputFile);

        $headers = $rows[0];

        // Should have language-specific columns.
        $frHeaders = array_filter($headers, fn($h) => strpos($h, '@fr') !== false);
        $enHeaders = array_filter($headers, fn($h) => strpos($h, '@en') !== false);

        $this->assertNotEmpty($frHeaders, 'Should have French language columns');
        $this->assertNotEmpty($enHeaders, 'Should have English language columns');
    }

    /**
     * Test value_per_column combined with format_fields_labels.
     */
    public function testValuePerColumnWithFormatFieldsLabels(): void
    {
        // Create an item with multiple subjects.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Subject 1'],
                ['type' => 'literal', '@value' => 'Subject 2'],
            ],
        ]);

        $exporter = $this->createExporter('CSV Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:subject'],
                'value_per_column' => true,
                'separator' => ' | ',
                'query' => ['id' => $item->id()],
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $outputFile = $this->getExportFilePath($export->getId());
        $this->assertNotNull($outputFile);
        $rows = $this->parseCsvFile($outputFile);

        // Should export successfully with multiple columns.
        $this->assertGreaterThan(1, count($rows));
        $headers = $rows[0];
        $subjectCount = count(array_filter($headers, fn($h) => $h === 'dcterms:subject'));
        $this->assertEquals(2, $subjectCount);
    }

    /**
     * Test that value_per_column=false keeps original behavior.
     */
    public function testValuePerColumnDisabled(): void
    {
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Subject 1'],
                ['type' => 'literal', '@value' => 'Subject 2'],
            ],
        ]);

        $exporter = $this->createExporter('CSV Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:subject'],
                'value_per_column' => false,
                'separator' => ' | ',
                'query' => ['id' => $item->id()],
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $outputFile = $this->getExportFilePath($export->getId());
        $this->assertNotNull($outputFile);
        $rows = $this->parseCsvFile($outputFile);

        // Headers should have only 1 dcterms:subject column.
        $headers = $rows[0];
        $subjectCount = count(array_filter($headers, fn($h) => $h === 'dcterms:subject'));
        $this->assertEquals(1, $subjectCount, 'Should have only 1 dcterms:subject column');

        // Values should be joined with separator.
        $dataRow = $rows[1];
        $subjectIndex = array_search('dcterms:subject', $headers);
        $this->assertStringContainsString('Subject 1', $dataRow[$subjectIndex]);
        $this->assertStringContainsString('Subject 2', $dataRow[$subjectIndex]);
        $this->assertStringContainsString(' | ', $dataRow[$subjectIndex]);
    }

    /**
     * Test column_metadata without value_per_column groups values by language.
     *
     * When column_metadata is set but value_per_column is false, values should
     * be grouped by metadata (e.g., language) and joined with separator.
     */
    public function testColumnMetadataOnlyByLanguage(): void
    {
        // Create an item with subjects in different languages.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Sujet un', '@language' => 'fr'],
                ['type' => 'literal', '@value' => 'Sujet deux', '@language' => 'fr'],
                ['type' => 'literal', '@value' => 'English subject', '@language' => 'en'],
                ['type' => 'literal', '@value' => 'Another English', '@language' => 'en'],
            ],
        ]);

        $exporter = $this->createExporter('CSV Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:subject'],
                'value_per_column' => false,
                'column_metadata' => ['language'],
                'separator' => ' | ',
                'query' => ['id' => $item->id()],
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $outputFile = $this->getExportFilePath($export->getId());
        $this->assertNotNull($outputFile);
        $rows = $this->parseCsvFile($outputFile);

        $headers = $rows[0];

        // Should have language-specific columns.
        $frHeaders = array_filter($headers, fn($h) => strpos($h, '@fr') !== false);
        $enHeaders = array_filter($headers, fn($h) => strpos($h, '@en') !== false);

        $this->assertNotEmpty($frHeaders, 'Should have French language column');
        $this->assertNotEmpty($enHeaders, 'Should have English language column');

        // Each language column should contain joined values.
        $dataRow = $rows[1];

        // Find the French column.
        $frIndex = null;
        foreach ($headers as $i => $h) {
            if (strpos($h, '@fr') !== false && strpos($h, 'dcterms:subject') !== false) {
                $frIndex = $i;
                break;
            }
        }

        // Find the English column.
        $enIndex = null;
        foreach ($headers as $i => $h) {
            if (strpos($h, '@en') !== false && strpos($h, 'dcterms:subject') !== false) {
                $enIndex = $i;
                break;
            }
        }

        if ($frIndex !== null) {
            // French values should be joined.
            $this->assertStringContainsString('Sujet un', $dataRow[$frIndex]);
            $this->assertStringContainsString('Sujet deux', $dataRow[$frIndex]);
            $this->assertStringContainsString(' | ', $dataRow[$frIndex]);
        }

        if ($enIndex !== null) {
            // English values should be joined.
            $this->assertStringContainsString('English subject', $dataRow[$enIndex]);
            $this->assertStringContainsString('Another English', $dataRow[$enIndex]);
            $this->assertStringContainsString(' | ', $dataRow[$enIndex]);
        }
    }

    /**
     * Test column_metadata with visibility groups public and private values.
     */
    public function testColumnMetadataOnlyByVisibility(): void
    {
        // Create an item with public and private subjects.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Item']],
            'dcterms:subject' => [
                ['type' => 'literal', '@value' => 'Public Subject 1', 'is_public' => true],
                ['type' => 'literal', '@value' => 'Public Subject 2', 'is_public' => true],
                ['type' => 'literal', '@value' => 'Private Subject', 'is_public' => false],
            ],
        ]);

        $exporter = $this->createExporter('CSV Test', CsvWriter::class, $this->getCsvWriterConfig());
        $export = $this->createExport($exporter, [
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title', 'dcterms:subject'],
                'value_per_column' => false,
                'column_metadata' => ['visibility'],
                'separator' => ' | ',
                'query' => ['id' => $item->id()],
            ],
        ]);

        $args = ['bulk_export_id' => $export->getId()];
        $job = $this->runJob(ExportJob::class, $args);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $outputFile = $this->getExportFilePath($export->getId());
        $this->assertNotNull($outputFile);
        $rows = $this->parseCsvFile($outputFile);

        $headers = $rows[0];

        // Should have private column indicator.
        $privateHeaders = array_filter($headers, fn($h) => strpos($h, '[private]') !== false);
        $this->assertNotEmpty($privateHeaders, 'Should have private column');
    }

    /**
     * Get the export file path after job completion.
     */
    protected function getExportFilePath(int $exportId): ?string
    {
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

        if (mb_substr($filename, 0, 1) === '/') {
            return $filename;
        }

        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return $basePath . '/bulk_export/' . $filename;
    }
}
