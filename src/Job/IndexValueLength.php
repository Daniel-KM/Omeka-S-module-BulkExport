<?php declare(strict_types=1);

namespace BulkExport\Job;

use Omeka\Job\AbstractJob;

/**
 * Populate the value_data table with existing data.
 *
 * This job fills the value_data table for values that existed before the
 * triggers were created. New values are handled automatically by triggers.
 *
 * Supports two modes via the 'mode' argument:
 * - 'all' (default): Truncate value_data and reindex all values
 * - 'missing': Only index values not yet in value_data
 */
class IndexValueLength extends AbstractJob
{
    /**
     * @var int
     */
    protected $batchSize = 100000;

    public function perform(): void
    {
        /**
         * @var \Laminas\Log\Logger $logger
         * @var \Doctrine\DBAL\Connection $connection
         */

        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $connection = $services->get('Omeka\Connection');

        // Mode: 'all' (default) or 'missing'.
        $mode = $this->getArg('mode') ?? 'all';

        // Check if table exists, create if needed.
        $tableExists = $connection->executeQuery(
            'SHOW TABLES LIKE "value_data";'
        )->fetchOne();

        if (!$tableExists) {
            $sqls = <<<'SQL'
                CREATE TABLE `value_data` (
                    `id` INT NOT NULL,
                    `length` INT UNSIGNED NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_value_length` (`length`),
                    CONSTRAINT `FK_value_data_value` FOREIGN KEY (`id`) REFERENCES `value` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                # Triggers to keep value_data synchronized with value table.
                # IFNULL handles NULL values (length = 0).
                CREATE TRIGGER `tr_value_data_insert` AFTER INSERT ON `value`
                FOR EACH ROW
                INSERT INTO `value_data` (`id`, `length`) VALUES (NEW.`id`, IFNULL(CHAR_LENGTH(NEW.`value`), 0));

                CREATE TRIGGER `tr_value_data_update` AFTER UPDATE ON `value`
                FOR EACH ROW
                UPDATE `value_data` SET `length` = IFNULL(CHAR_LENGTH(NEW.`value`), 0) WHERE `id` = NEW.`id`;
                SQL;
            foreach (array_filter(explode(";\n", $sqls)) as $sql) {
                try {
                    $connection->executeStatement($sql);
                } catch (\Exception $e) {
                    // Already exists.
                }
            }
        }

        if ($mode === 'all') {
            $this->indexAll($connection, $logger);
        } else {
            $this->indexMissing($connection, $logger);
        }
    }

    /**
     * Reindex all values (truncate and rebuild).
     */
    protected function indexAll($connection, $logger): void
    {
        // Truncate is faster than DELETE and resets auto-increment.
        // Temporarily disable foreign key checks to allow TRUNCATE.
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE `value_data`');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $logger->info('Table value_data truncated for full reindex.'); // @translate

        // Count all values.
        $totalToIndex = (int) $connection->executeQuery(
            'SELECT COUNT(id) FROM `value`'
        )->fetchOne();

        if ($totalToIndex === 0) {
            $logger->info('No values to index.'); // @translate
            return;
        }

        $logger->info(
            'Indexing {count} values into value_data table (full reindex).', // @translate
            ['count' => $totalToIndex]
        );

        $indexed = 0;
        $lastId = 0;

        while (true) {
            if ($this->shouldStop()) {
                $logger->warn(
                    'Job stopped. Indexed {count} values.', // @translate
                    ['count' => $indexed]
                );
                return;
            }

            // Insert all values in batches using id > lastId for pagination.
            // This is more efficient than OFFSET for large tables.
            $sql = <<<SQL
                INSERT INTO `value_data` (`id`, `length`)
                SELECT id, IFNULL(CHAR_LENGTH(`value`), 0)
                FROM `value`
                WHERE id > {$lastId}
                ORDER BY id
                LIMIT {$this->batchSize}
                SQL;
            $count = $connection->executeStatement($sql);

            if ($count === 0) {
                break;
            }

            // Get the last inserted id for next batch.
            $lastId = (int) $connection->executeQuery(
                'SELECT MAX(id) FROM `value_data`'
            )->fetchOne();

            $indexed += $count;

            $logger->info(
                'Indexed {indexed} / {total} values.', // @translate
                ['indexed' => $indexed, 'total' => $totalToIndex]
            );
        }

        $logger->info(
            'Completed full reindex of {count} values into value_data.', // @translate
            ['count' => $indexed]
        );
    }

    /**
     * Index only missing values (incremental).
     */
    protected function indexMissing($connection, $logger): void
    {
        // Count values not yet indexed.
        $sql = <<<'SQL'
            SELECT COUNT(v.id)
            FROM `value` v
            LEFT JOIN `value_data` vd ON v.id = vd.id
            WHERE vd.id IS NULL
            SQL;
        $totalToIndex = (int) $connection->executeQuery($sql)->fetchOne();

        if ($totalToIndex === 0) {
            $logger->info('All values are already indexed in value_data.'); // @translate
            return;
        }

        $logger->info(
            'Indexing {count} missing values into value_data table.', // @translate
            ['count' => $totalToIndex]
        );

        $indexed = 0;

        while (true) {
            if ($this->shouldStop()) {
                $logger->warn(
                    'Job stopped. Indexed {count} values.', // @translate
                    ['count' => $indexed]
                );
                return;
            }

            // Insert missing values in batches.
            // IFNULL handles NULL values (length = 0).
            $sql = <<<SQL
                INSERT INTO `value_data` (`id`, `length`)
                SELECT v.id, IFNULL(CHAR_LENGTH(v.`value`), 0)
                FROM `value` v
                LEFT JOIN `value_data` vd ON v.id = vd.id
                WHERE vd.id IS NULL
                LIMIT {$this->batchSize}
                SQL;
            $count = $connection->executeStatement($sql);

            if ($count === 0) {
                break;
            }

            $indexed += $count;

            $logger->info(
                'Indexed {indexed} / {total} values.', // @translate
                ['indexed' => $indexed, 'total' => $totalToIndex]
            );
        }

        $logger->info(
            'Completed indexing {count} missing values into value_data.', // @translate
            ['count' => $indexed]
        );
    }
}
