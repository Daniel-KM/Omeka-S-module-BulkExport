<?php declare(strict_types=1);

namespace BulkExportTest;

use BulkExport\Entity\Export;
use BulkExport\Entity\Exporter;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Entity\Job;

/**
 * Shared test helpers for BulkExport module tests.
 */
trait BulkExportTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array List of created resource IDs for cleanup.
     */
    protected $createdResources = [];

    /**
     * @var array List of created exporter IDs for cleanup.
     */
    protected $createdExporters = [];

    /**
     * @var array List of created export IDs for cleanup.
     */
    protected $createdExports = [];

    /**
     * @var string Temporary file path.
     */
    protected $tempFile;

    /**
     * Get the API manager.
     */
    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    /**
     * Get the entity manager.
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Get the database connection.
     */
    protected function getConnection()
    {
        return $this->getServiceLocator()->get('Omeka\Connection');
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Create a test item.
     *
     * @param array $data Item data with property terms as keys.
     * @return ItemRepresentation
     */
    protected function createItem(array $data): ItemRepresentation
    {
        $itemData = [];
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        foreach ($data as $term => $values) {
            if (strpos($term, ':') === false) {
                $itemData[$term] = $values;
                continue;
            }

            $propertyId = $easyMeta->propertyId($term);
            if (!$propertyId) {
                continue;
            }

            $itemData[$term] = [];
            foreach ($values as $value) {
                $valueData = [
                    'type' => $value['type'] ?? 'literal',
                    'property_id' => $propertyId,
                ];
                if (isset($value['@value'])) {
                    $valueData['@value'] = $value['@value'];
                }
                if (isset($value['@id'])) {
                    $valueData['@id'] = $value['@id'];
                }
                if (isset($value['@language'])) {
                    $valueData['@language'] = $value['@language'];
                }
                if (isset($value['o:label'])) {
                    $valueData['o:label'] = $value['o:label'];
                }
                if (isset($value['is_public'])) {
                    $valueData['is_public'] = $value['is_public'];
                }
                $itemData[$term][] = $valueData;
            }
        }

        $response = $this->api()->create('items', $itemData);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];

        return $item;
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
     * @var \Exception|null Last exception from job execution.
     */
    protected $lastJobException;

    /**
     * Run a job synchronously for testing.
     *
     * @param string $jobClass Job class name.
     * @param array $args Job arguments.
     * @param bool $expectError If true, don't rethrow exceptions.
     * @return Job
     */
    protected function runJob(string $jobClass, array $args, bool $expectError = false): Job
    {
        $this->lastJobException = null;
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $auth = $services->get('Omeka\AuthenticationService');

        $job = new Job();
        $job->setStatus(Job::STATUS_STARTING);
        $job->setClass($jobClass);
        $job->setArgs($args);
        $job->setOwner($auth->getIdentity());

        $entityManager->persist($job);
        $entityManager->flush();

        $jobClass = $job->getClass();
        $jobInstance = new $jobClass($job, $services);
        $job->setStatus(Job::STATUS_IN_PROGRESS);
        $job->setStarted(new \DateTime('now'));
        $entityManager->flush();

        try {
            $jobInstance->perform();
            if ($job->getStatus() === Job::STATUS_IN_PROGRESS) {
                $job->setStatus(Job::STATUS_COMPLETED);
            }
        } catch (\Throwable $e) {
            $this->lastJobException = $e;
            $job->setStatus(Job::STATUS_ERROR);
            if (!$expectError) {
                throw $e;
            }
        }

        $job->setEnded(new \DateTime('now'));
        $entityManager->flush();

        return $job;
    }

    /**
     * Get the last exception from job execution.
     */
    protected function getLastJobException(): ?\Exception
    {
        return $this->lastJobException;
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        // Delete created items.
        foreach ($this->createdResources as $resource) {
            try {
                $this->api()->delete($resource['type'], $resource['id']);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdResources = [];

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
