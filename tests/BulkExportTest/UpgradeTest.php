<?php declare(strict_types=1);

namespace BulkExportTest;

use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for database migrations in upgrade.php.
 *
 * @group integration
 */
class UpgradeTest extends AbstractHttpControllerTestCase
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
     * Test migration 3.4.51: rename 'writer' key to 'formatter' in JSON config.
     */
    public function testMigrationRenameWriterKeyInConfig(): void
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // Insert test exporter with old 'writer' key in config.
        $oldConfig = json_encode([
            'writer' => [
                'resource_types' => ['o:Item'],
                'metadata' => ['o:id', 'dcterms:title'],
                'separator' => ' | ',
            ],
        ]);

        $sql = <<<'SQL'
            INSERT INTO `bulk_exporter` (`owner_id`, `label`, `formatter`, `config`)
            VALUES (1, 'Test Migration', 'csv', :config)
            SQL;
        $connection->executeStatement($sql, ['config' => $oldConfig]);
        $exporterId = (int) $connection->lastInsertId();

        // Run the migration SQL.
        $sql = <<<'SQL'
            UPDATE `bulk_exporter`
            SET `config` = JSON_SET(
                JSON_REMOVE(`config`, '$.writer'),
                '$.formatter',
                JSON_EXTRACT(`config`, '$.writer')
            )
            WHERE `id` = :id AND JSON_EXTRACT(`config`, '$.writer') IS NOT NULL;
            SQL;
        $connection->executeStatement($sql, ['id' => $exporterId]);

        // Verify the migration result.
        $sql = "SELECT `config` FROM `bulk_exporter` WHERE `id` = :id";
        $result = $connection->executeQuery($sql, ['id' => $exporterId])->fetchAssociative();
        $newConfig = json_decode($result['config'], true);

        $this->assertArrayHasKey('formatter', $newConfig, 'Config should have "formatter" key after migration');
        $this->assertArrayNotHasKey('writer', $newConfig, 'Config should not have "writer" key after migration');
        $this->assertEquals(['o:Item'], $newConfig['formatter']['resource_types']);
        $this->assertEquals(['o:id', 'dcterms:title'], $newConfig['formatter']['metadata']);

        // Cleanup.
        $connection->executeStatement("DELETE FROM `bulk_exporter` WHERE `id` = :id", ['id' => $exporterId]);
    }

    /**
     * Test migration 3.4.51: rename 'writer' key to 'formatter' in JSON params.
     */
    public function testMigrationRenameWriterKeyInParams(): void
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // First create an exporter.
        $sql = <<<'SQL'
            INSERT INTO `bulk_exporter` (`owner_id`, `label`, `formatter`, `config`)
            VALUES (1, 'Test Migration Params', 'csv', '{"formatter":{}}')
            SQL;
        $connection->executeStatement($sql);
        $exporterId = (int) $connection->lastInsertId();

        // Insert test export with old 'writer' key in params.
        $oldParams = json_encode([
            'writer' => [
                'resource_types' => ['o:Item'],
                'query' => ['id' => 1],
            ],
        ]);

        $sql = <<<'SQL'
            INSERT INTO `bulk_export` (`exporter_id`, `owner_id`, `params`)
            VALUES (:exporter_id, 1, :params)
            SQL;
        $connection->executeStatement($sql, [
            'exporter_id' => $exporterId,
            'params' => $oldParams,
        ]);
        $exportId = (int) $connection->lastInsertId();

        // Run the migration SQL.
        $sql = <<<'SQL'
            UPDATE `bulk_export`
            SET `params` = JSON_SET(
                JSON_REMOVE(`params`, '$.writer'),
                '$.formatter',
                JSON_EXTRACT(`params`, '$.writer')
            )
            WHERE `id` = :id AND JSON_EXTRACT(`params`, '$.writer') IS NOT NULL;
            SQL;
        $connection->executeStatement($sql, ['id' => $exportId]);

        // Verify the migration result.
        $sql = "SELECT `params` FROM `bulk_export` WHERE `id` = :id";
        $result = $connection->executeQuery($sql, ['id' => $exportId])->fetchAssociative();
        $newParams = json_decode($result['params'], true);

        $this->assertArrayHasKey('formatter', $newParams, 'Params should have "formatter" key after migration');
        $this->assertArrayNotHasKey('writer', $newParams, 'Params should not have "writer" key after migration');
        $this->assertEquals(['o:Item'], $newParams['formatter']['resource_types']);

        // Cleanup.
        $connection->executeStatement("DELETE FROM `bulk_export` WHERE `id` = :id", ['id' => $exportId]);
        $connection->executeStatement("DELETE FROM `bulk_exporter` WHERE `id` = :id", ['id' => $exporterId]);
    }

    /**
     * Test PHP fallback migration for JSON key rename.
     */
    public function testMigrationPhpFallback(): void
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // Insert test exporter with old 'writer' key in config.
        $oldConfig = json_encode([
            'writer' => [
                'resource_types' => ['o:Item'],
                'format_fields' => 'label',
            ],
        ]);

        $sql = <<<'SQL'
            INSERT INTO `bulk_exporter` (`owner_id`, `label`, `formatter`, `config`)
            VALUES (1, 'Test PHP Fallback', 'csv', :config)
            SQL;
        $connection->executeStatement($sql, ['config' => $oldConfig]);
        $exporterId = (int) $connection->lastInsertId();

        // Simulate the PHP fallback logic from upgrade.php.
        $sql = "SELECT `id`, `config` FROM `bulk_exporter` WHERE `id` = :id";
        $row = $connection->executeQuery($sql, ['id' => $exporterId])->fetchAssociative();

        $config = json_decode($row['config'], true) ?: [];
        if (isset($config['writer'])) {
            $config['formatter'] = $config['writer'];
            unset($config['writer']);
            $updateSql = "UPDATE `bulk_exporter` SET `config` = :config WHERE `id` = :id";
            $connection->executeStatement($updateSql, [
                'config' => json_encode($config),
                'id' => $row['id'],
            ]);
        }

        // Verify the migration result.
        $sql = "SELECT `config` FROM `bulk_exporter` WHERE `id` = :id";
        $result = $connection->executeQuery($sql, ['id' => $exporterId])->fetchAssociative();
        $newConfig = json_decode($result['config'], true);

        $this->assertArrayHasKey('formatter', $newConfig, 'Config should have "formatter" key after PHP fallback');
        $this->assertArrayNotHasKey('writer', $newConfig, 'Config should not have "writer" key after PHP fallback');
        $this->assertEquals(['o:Item'], $newConfig['formatter']['resource_types']);

        // Cleanup.
        $connection->executeStatement("DELETE FROM `bulk_exporter` WHERE `id` = :id", ['id' => $exporterId]);
    }

    /**
     * Test that config without 'writer' key is not affected by migration.
     */
    public function testMigrationSkipsConfigWithoutWriterKey(): void
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // Insert test exporter with new 'formatter' key (already migrated).
        $newConfig = json_encode([
            'formatter' => [
                'resource_types' => ['o:Item'],
                'separator' => ' | ',
            ],
        ]);

        $sql = <<<'SQL'
            INSERT INTO `bulk_exporter` (`owner_id`, `label`, `formatter`, `config`)
            VALUES (1, 'Test Already Migrated', 'csv', :config)
            SQL;
        $connection->executeStatement($sql, ['config' => $newConfig]);
        $exporterId = (int) $connection->lastInsertId();

        // Run the migration SQL (should not affect this row).
        $sql = <<<'SQL'
            UPDATE `bulk_exporter`
            SET `config` = JSON_SET(
                JSON_REMOVE(`config`, '$.writer'),
                '$.formatter',
                JSON_EXTRACT(`config`, '$.writer')
            )
            WHERE `id` = :id AND JSON_EXTRACT(`config`, '$.writer') IS NOT NULL;
            SQL;
        $connection->executeStatement($sql, ['id' => $exporterId]);

        // Verify the config is unchanged.
        $sql = "SELECT `config` FROM `bulk_exporter` WHERE `id` = :id";
        $result = $connection->executeQuery($sql, ['id' => $exporterId])->fetchAssociative();
        $resultConfig = json_decode($result['config'], true);

        $this->assertArrayHasKey('formatter', $resultConfig, 'Config should still have "formatter" key');
        $this->assertArrayNotHasKey('writer', $resultConfig, 'Config should not have "writer" key');
        $this->assertEquals(['o:Item'], $resultConfig['formatter']['resource_types']);
        $this->assertEquals(' | ', $resultConfig['formatter']['separator']);

        // Cleanup.
        $connection->executeStatement("DELETE FROM `bulk_exporter` WHERE `id` = :id", ['id' => $exporterId]);
    }
}
