<?php declare(strict_types=1);

namespace BulkExportTest\Job;

use BulkExport\Job\IndexValueLength;
use Omeka\Entity\Job;
use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Unit and functional tests for the IndexValueLength job.
 */
class IndexValueLengthTest extends AbstractHttpControllerTestCase
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
     * Test job completes successfully.
     */
    public function testJobCompletes(): void
    {
        $job = $this->runJob(IndexValueLength::class, []);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test value_data table is created if it doesn't exist.
     */
    public function testValueDataTableIsCreated(): void
    {
        $connection = $this->getConnection();

        // Run the job to ensure table exists.
        $this->runJob(IndexValueLength::class, []);

        // Check table exists.
        $result = $connection->executeQuery(
            "SHOW TABLES LIKE 'value_data'"
        )->fetchOne();

        $this->assertNotFalse($result, 'value_data table should exist');
    }

    /**
     * Test values are indexed after job runs.
     *
     * @group integration
     */
    public function testValuesAreIndexed(): void
    {
        // Create a test item with a value.
        $testValue = 'This is a test value for indexing';
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => $testValue]],
        ]);

        // Run the indexing job.
        $job = $this->runJob(IndexValueLength::class, []);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        // Check the value was indexed.
        $connection = $this->getConnection();
        $sql = <<<'SQL'
            SELECT vd.length
            FROM value_data vd
            INNER JOIN value v ON v.id = vd.id
            WHERE v.value = :value
            SQL;
        $result = $connection->executeQuery($sql, ['value' => $testValue])->fetchOne();

        $expectedLength = mb_strlen($testValue);
        $this->assertEquals($expectedLength, $result, 'Value length should be indexed');
    }

    /**
     * Test job handles empty database gracefully.
     */
    public function testJobHandlesEmptyDatabase(): void
    {
        // Simply run the job with no values to index.
        $job = $this->runJob(IndexValueLength::class, []);

        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());
    }

    /**
     * Test index can be used for length queries.
     *
     * @group integration
     */
    public function testIndexUsedForLengthQueries(): void
    {
        // Create items with different value lengths.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Short']],
        ]);
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'This is a much longer title value']],
        ]);
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Medium length value']],
        ]);

        // Run the indexing job.
        $this->runJob(IndexValueLength::class, []);

        // Query using the index.
        $connection = $this->getConnection();
        $sql = <<<'SQL'
            SELECT COUNT(*)
            FROM value_data
            WHERE length >= :min_length
            SQL;
        $count = $connection->executeQuery($sql, ['min_length' => 10])->fetchOne();

        $this->assertGreaterThanOrEqual(2, $count, 'Should find values with length >= 10');
    }
}
