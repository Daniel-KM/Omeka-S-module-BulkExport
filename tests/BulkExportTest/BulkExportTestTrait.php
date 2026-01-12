<?php declare(strict_types=1);

namespace BulkExportTest;

use BulkExport\Entity\Export;
use BulkExport\Entity\Exporter;
use CommonTest\JobTestTrait;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * Shared test helpers for BulkExport module tests.
 *
 * Extends CommonTest\JobTestTrait with BulkExport-specific helpers.
 */
trait BulkExportTestTrait
{
    use JobTestTrait {
        JobTestTrait::cleanupResources as baseCleanupResources;
    }

    /**
     * @var array List of created exporter IDs for cleanup.
     */
    protected array $createdExporters = [];

    /**
     * @var array List of created export IDs for cleanup.
     */
    protected array $createdExports = [];

    /**
     * @var string|null Temporary file path.
     */
    protected ?string $tempFile = null;

    /**
     * Create a test item (wrapper for createTrackedItem with simpler API).
     *
     * @param array $data Item data with property terms as keys.
     * @return ItemRepresentation
     */
    protected function createItem(array $data): ItemRepresentation
    {
        return $this->createTrackedItem($data);
    }

    /**
     * Create multiple test items.
     *
     * @param int $count Number of items to create.
     * @param array $baseData Base data for items.
     * @return array List of created items.
     */
    protected function createItems(int $count, array $baseData = []): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $data = $baseData;
            if (!isset($data['dcterms:title'])) {
                $data['dcterms:title'] = [['type' => 'literal', '@value' => "Test Item $i"]];
            }
            $items[] = $this->createItem($data);
        }
        return $items;
    }

    /**
     * Create an exporter entity.
     *
     * @param string $label Exporter label.
     * @param string $formatterName Formatter name (e.g., 'csv', 'tsv').
     * @param array $formatterConfig Formatter configuration.
     * @return Exporter
     */
    protected function createExporter(string $label, string $formatterName, array $formatterConfig = []): Exporter
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $entityManager = $this->getEntityManager();

        $exporter = new Exporter();
        $exporter->setOwner($auth->getIdentity());
        $exporter->setLabel($label);
        $exporter->setFormatter($formatterName);
        $exporter->setConfig($formatterConfig);

        $entityManager->persist($exporter);
        $entityManager->flush();

        $this->createdExporters[] = $exporter->getId();

        return $exporter;
    }

    /**
     * Create an export entity.
     *
     * @param Exporter $exporter Exporter to use.
     * @param array $params Formatter parameters.
     * @return Export
     */
    protected function createExport(Exporter $exporter, array $params = []): Export
    {
        $entityManager = $this->getEntityManager();

        $export = new Export();
        $export->setExporter($exporter);
        $export->setParams($params);

        $entityManager->persist($export);
        $entityManager->flush();

        $this->createdExports[] = $export->getId();

        return $export;
    }

    /**
     * Create a temporary file for export output.
     *
     * @param string $extension File extension.
     * @return string File path.
     */
    protected function createTempFile(string $extension = 'csv'): string
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'omk_bke_') . '.' . $extension;
        return $this->tempFile;
    }

    /**
     * Get a fixture file content.
     *
     * @param string $name Fixture filename.
     * @return string
     */
    protected function getFixture(string $name): string
    {
        $path = __DIR__ . '/fixtures/' . $name;
        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture not found: $path");
        }
        return file_get_contents($path);
    }

    /**
     * Get the path to the fixtures directory.
     */
    protected function getFixturesPath(): string
    {
        return __DIR__ . '/fixtures';
    }

    /**
     * Get the database connection.
     */
    protected function getConnection()
    {
        return $this->getServiceLocator()->get('Omeka\Connection');
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        // Clean up base resources (items, etc.)
        $this->baseCleanupResources();

        // Delete created exports.
        $entityManager = $this->getEntityManager();
        foreach ($this->createdExports as $exportId) {
            try {
                $export = $entityManager->find(Export::class, $exportId);
                if ($export) {
                    $entityManager->remove($export);
                }
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdExports = [];

        // Delete created exporters.
        foreach ($this->createdExporters as $exporterId) {
            try {
                $exporter = $entityManager->find(Exporter::class, $exporterId);
                if ($exporter) {
                    $entityManager->remove($exporter);
                }
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdExporters = [];

        $entityManager->flush();

        // Clean up temp files.
        if ($this->tempFile && file_exists($this->tempFile)) {
            @unlink($this->tempFile);
            $this->tempFile = null;
        }
    }

    /**
     * Get CSV formatter default configuration.
     */
    protected function getCsvFormatterConfig(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'separator' => ' | ',
        ];
    }

    /**
     * Get TSV formatter default configuration.
     */
    protected function getTsvFormatterConfig(): array
    {
        return [
            'delimiter' => "\t",
            'enclosure' => chr(0),
            'escape' => chr(0),
            'separator' => ' | ',
        ];
    }

    /**
     * Parse CSV file content.
     *
     * @param string $filepath Path to CSV file.
     * @param string $delimiter CSV delimiter.
     * @return array Parsed rows.
     */
    protected function parseCsvFile(string $filepath, string $delimiter = ','): array
    {
        $rows = [];
        if (($handle = fopen($filepath, 'r')) !== false) {
            $isFirstRow = true;
            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                // Strip BOM from first cell of first row if present.
                if ($isFirstRow && count($data) > 0) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                    $isFirstRow = false;
                }
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }

    /**
     * Assert that a CSV file contains expected headers.
     *
     * @param string $filepath Path to CSV file.
     * @param array $expectedHeaders Expected header names.
     * @param string $delimiter CSV delimiter.
     */
    protected function assertCsvHeaders(string $filepath, array $expectedHeaders, string $delimiter = ','): void
    {
        $rows = $this->parseCsvFile($filepath, $delimiter);
        $this->assertNotEmpty($rows, 'CSV file is empty');
        $this->assertEquals($expectedHeaders, $rows[0], 'CSV headers do not match');
    }

    /**
     * Assert that a CSV file contains expected number of data rows.
     *
     * @param string $filepath Path to CSV file.
     * @param int $expectedCount Expected number of data rows (excluding header).
     * @param string $delimiter CSV delimiter.
     */
    protected function assertCsvRowCount(string $filepath, int $expectedCount, string $delimiter = ','): void
    {
        $rows = $this->parseCsvFile($filepath, $delimiter);
        $actualCount = count($rows) - 1; // Exclude header row
        $this->assertEquals($expectedCount, $actualCount, "Expected $expectedCount data rows, got $actualCount");
    }
}
