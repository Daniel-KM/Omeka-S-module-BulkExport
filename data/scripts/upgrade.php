<?php declare(strict_types=1);

namespace BulkExport;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$config = $services->get('Config');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$localConfig = include dirname(__DIR__, 2) . '/config/module.config.php';

$failExporters = [];

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.76')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.76'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (!$this->checkModuleActiveVersion('Log', '3.4.32')) {
    $message = new PsrMessage(
        'The module {module} should be upgraded to version {version} or later.', // @translate
        ['module' => 'Log', 'version' => '3.4.32']
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
}

if (version_compare($oldVersion, '3.0.7', '<')) {
    $directory = new \RecursiveDirectoryIterator(dirname(__DIR__) . '/exporters', \RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new \RecursiveIteratorIterator($directory);
    // The format has changed, so import them later.
    $failExporters = [];
    foreach (array_keys($iterator) as $filepath) {
        $failExporters[] = $filepath;
    }
}

if (version_compare($oldVersion, '3.0.8', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export`
            ADD `comment` VARCHAR(190) DEFAULT NULL AFTER `job_id`;
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.0.7', '>')
    && version_compare($oldVersion, '3.0.12', '<')
) {
    $failExporters[] = dirname(__DIR__) . '/exporters/txt.php';
    $failExporters[] = dirname(__DIR__) . '/exporters/odt.php';
}

if (version_compare($oldVersion, '3.0.8', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export`
            ADD `comment` VARCHAR(190) DEFAULT NULL AFTER `job_id`;
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.12.5', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export` DROP FOREIGN KEY FK_625A30FDB4523DE5;
        SQL;
    $connection->executeStatement($sql);
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export` DROP FOREIGN KEY FK_625A30FDBE04EA9;
        SQL;
    $connection->executeStatement($sql);
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export`
        CHANGE `exporter_id` `exporter_id` INT DEFAULT NULL,
        CHANGE `job_id` `job_id` INT DEFAULT NULL,
        CHANGE `comment` `comment` VARCHAR(190) DEFAULT NULL,
        CHANGE `writer_params` `writer_params` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
        CHANGE `filename` `filename` VARCHAR(255) DEFAULT NULL;
        SQL;
    $connection->executeStatement($sql);
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export` ADD CONSTRAINT FK_625A30FDB4523DE5 FOREIGN KEY (`exporter_id`) REFERENCES `bulk_exporter` (`id`) ON DELETE SET NULL;
        SQL;
    $connection->executeStatement($sql);
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export` ADD CONSTRAINT FK_625A30FDBE04EA9 FOREIGN KEY (`job_id`) REFERENCES `job` (`id`) ON DELETE SET NULL;
        SQL;
    $connection->executeStatement($sql);
    $sql = <<<'SQL'
        ALTER TABLE `bulk_exporter`
        CHANGE `owner_id` `owner_id` INT DEFAULT NULL,
        CHANGE `label` `label` VARCHAR(190) DEFAULT NULL,
        CHANGE `writer_class` `writer_class` VARCHAR(190) DEFAULT NULL,
        CHANGE `writer_config` `writer_config` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.12.12', '<')) {
    // @link https://www.doctrine-project.org/projects/doctrine-dbal/en/2.6/reference/types.html#array-types
    $sql = <<<'SQL'
        ALTER TABLE `bulk_export`
        CHANGE `writer_params` `writer_params` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)';
        SQL;
    $connection->executeStatement($sql);
    $sql = <<<'SQL'
        ALTER TABLE `bulk_exporter`
        CHANGE `writer_config` `writer_config` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)';
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.13.0', '<')) {
    $sqls = <<<'SQL'
        ALTER TABLE `bulk_export`
            ADD `owner_id` INT DEFAULT NULL AFTER `exporter_id`,
            CHANGE `exporter_id` `exporter_id` INT DEFAULT NULL,
            CHANGE `job_id` `job_id` INT DEFAULT NULL AFTER `owner_id`,
            CHANGE `comment` `comment` VARCHAR(190) DEFAULT NULL,
            CHANGE `writer_params` `writer_params` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
            CHANGE `filename` `filename` VARCHAR(255) DEFAULT NULL;
        ALTER TABLE `bulk_export`
            ADD CONSTRAINT FK_625A30FD7E3C61F9 FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;
        CREATE INDEX IDX_625A30FD7E3C61F9 ON `bulk_export` (`owner_id`);
        ALTER TABLE `bulk_exporter`
            CHANGE `owner_id` `owner_id` INT DEFAULT NULL AFTER `id`,
            CHANGE `label` `label` VARCHAR(190) DEFAULT NULL,
            CHANGE `writer_class` `writer_class` VARCHAR(190) DEFAULT NULL,
            CHANGE `writer_config` `writer_config` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)';
        SQL;
    foreach (array_filter(explode(";\n", $sqls)) as $sql) {
        $connection->executeStatement($sql);
    }
}

if (version_compare($oldVersion, '3.4.19', '<')) {
    $message = new PsrMessage(
        'It is now possible to export resources as GeoJSON for values filled via Value Suggest Geonames.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.21', '<')) {
    $message = new PsrMessage(
        'It is now possible to export into a specific directory with a specific filename.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.23', '<')) {
    $message = new PsrMessage(
        'It is now possible to export via the api endpoint: just add an extension to it, like for admin or site view, for example "/api/items.ods" or "/api-local/items/151.odt" (from module Api Info).' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.27', '<')) {
    $sqls = <<<'SQL'
        UPDATE `bulk_exporter`
            SET
                `writer_config` = '{}'
            WHERE `writer_config` IS NULL OR `writer_config` = "";
        UPDATE `bulk_export`
            SET
                `writer_params` = '{}'
            WHERE `writer_params` IS NULL OR `writer_params` = "";
        ALTER TABLE `bulk_exporter`
            CHANGE `label` `label` VARCHAR(190) NOT NULL AFTER `owner_id`,
            CHANGE `writer_class` `writer` VARCHAR(190) NOT NULL AFTER `label`,
            CHANGE `writer_config` `config` LONGTEXT NOT NULL COMMENT '(DC2Type:json)' AFTER `writer`;
        ALTER TABLE `bulk_export`
            CHANGE `writer_params` `params` LONGTEXT NOT NULL COMMENT '(DC2Type:json)' AFTER `comment`,
            CHANGE `filename` `filename` VARCHAR(760) DEFAULT NULL AFTER `params`;
        UPDATE `bulk_exporter`
            SET
                `config` = CONCAT('{"writer":', `config`, '}')
            WHERE `config` IS NOT NULL;
            UPDATE `bulk_export`
            SET
                `params` = CONCAT('{"writer":', `params`, '}')
            WHERE `params` IS NOT NULL;
        UPDATE `bulk_exporter`
            SET
                `config` =
                    REPLACE(
                    REPLACE(
                    `config`,
                    "properties_small", "properties_max_5000"),
                    "properties_large", "properties_min_5000");
        UPDATE `bulk_export`
            SET
                `params` =
                    REPLACE(
                    REPLACE(
                    `params`,
                    "properties_small", "properties_max_5000"),
                    "properties_large", "properties_min_5000");
        SQL;
    foreach (array_filter(explode(";\n", $sqls)) as $sql) {
        $connection->executeStatement($sql);
    }

    $message = new PsrMessage(
        'It is now possible to store an exporter as a task.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to do an incremental export.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.29', '<')) {
    // TODO Fix upgrade for annotation body and target (but not yet used anyway).
    $sql = <<<'SQL'
        UPDATE `bulk_export`
        SET
            `params` =
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                REPLACE(
                    `params`,
                    '"o:item[o:id]"',
                    '"o:item/o:id"'
                ),
                    '"o:item[dcterms:identifier]"',
                    '"o:item/dcterms:identifier"'
                ),
                    '"o:item[dcterms:title]"',
                    '"o:item/dcterms:title"'
                ),
        
                    '"o:item_set[o:id]"',
                    '"o:item_set/o:id"'
                ),
                    '"o:item_set[dcterms:identifier]"',
                    '"o:item_set/dcterms:identifier"'
                ),
                    '"o:item_set[dcterms:title]"',
                    '"o:item_set/dcterms:title"'
                ),
        
                    '"o:media[o:id]"',
                    '"o:media/o:id"'
                ),
                    '"o:media[file]"',
                    '"o:media/file"'
                ),
                    '"o:media[source]"',
                    '"o:media/o:source"'
                ),
                    '"o:media[dcterms:identifier]"',
                    '"o:media/dcterms:identifier"'
                ),
                    '"o:media[dcterms:title]"',
                    '"o:media/dcterms:title"'
                ),
                    '"o:media[url]"',
                    '"o:media/url"'
                ),
                    '"o:media[media_type]"',
                    '"o:media/o:media_type"'
                ),
                    '"o:media[size]"',
                    '"o:media/o:size"'
                ),
                    '"o:media[original_url]"',
                    '"o:media/original_url"'
                ),
        
                    '"o:resource[o:id]"',
                    '"o:resource/o:id"'
                ),
                    '"o:resource[dcterms:identifier]"',
                    '"o:resource/dcterms:identifier"'
                ),
                    '"o:resource[dcterms:title]"',
                    '"o:resource/dcterms:title"'
                ),
        
                    '"o:owner[o:id]"',
                    '"o:owner/o:id"'
                ),
                    '"o:owner[o:email]"',
                    '"o:owner/o:email"'
                ),
        
                    '"oa:hasBody[',
                    '"oa:hasBody/'
                ),
                    '"oa:hasTarget[',
                    '"oa:hasTarget/'
                )
            ;
        SQL;
    $connection->executeStatement($sql);

    $replace = [
        '`bulk_export`' => '`bulk_exporter`',
        '`params`' => '`config`',
    ];
    $connection->executeStatement(strtr($sql, $replace));

    $message = new PsrMessage(
        'The config of exporters has been upgraded to a new format. You may check them if you use a complex config.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.32', '<')) {
    /** @var \Omeka\Settings\SiteSettings $siteSettings */
    $siteSettings = $services->get('Omeka\Settings\Site');
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    $bulkExportViews = $localConfig['bulkexport']['site_settings']['bulkexport_views'];
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('bulkexport_views', $bulkExportViews);
    }

    $message = new PsrMessage(
        'New resource page blocks have been added to display a button to export current resources.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'A new option has been added to display the exporters automatically in selected pages for themes that don’t manage resource page blocks. Check your site if needed.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.35', '<')) {
    $sqls = <<<'SQL'
        UPDATE `bulk_exporter`
        SET
            `config` =
                REPLACE(
                REPLACE(
                `config`,
                "properties_small", "properties_max_5000"),
                "properties_large", "properties_min_5000")
        ;
        UPDATE `bulk_export`
        SET
            `params` =
                REPLACE(
                REPLACE(
                `params`,
                "properties_small", "properties_max_5000"),
                "properties_large", "properties_min_5000");
        SQL;
    foreach (array_filter(explode(";\n", $sqls)) as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Exception $e) {
        }
    }

    $message = new PsrMessage(
        'The option "divclass" was removed from blocks. Check your theme if you customized it.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.38', '<')) {
    $sql = <<<'SQL'
        CREATE TABLE `bulk_shaper` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `owner_id` INT DEFAULT NULL,
            `label` VARCHAR(190) NOT NULL,
            `config` LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
            `created` DATETIME NOT NULL,
            `modified` DATETIME DEFAULT NULL,
            INDEX `IDX_C40AB3ED7E3C61F9` (`owner_id`),
            PRIMARY KEY(`id`)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        ALTER TABLE `bulk_shaper` ADD CONSTRAINT `FK_C40AB3ED7E3C61F9` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Table exists.
    }

    $message = new PsrMessage(
        'It is now possible to define a specific format for each metadata.' // @translate
    );
    $messenger->addSuccess($message);

    // Create value_data table and triggers for indexing value length.
    $tableExists = $connection->executeQuery("SHOW TABLES LIKE 'value_data';")->fetchOne();
    if (!$tableExists) {
        // Dispatch job to populate the table with existing data.
        require_once dirname(__DIR__, 2) . '/src/Job/IndexValueLength.php';
        $dispatcher = $services->get('Omeka\Job\Dispatcher');
        $dispatcher->dispatch(\BulkExport\Job\IndexValueLength::class);

        $message = new PsrMessage(
            'A table has been created to index value lengths for quicker exports. A background job is populating it.' // @translate
        );
        $messenger->addSuccess($message);
    }
}

if (version_compare($oldVersion, '3.4.39', '<')) {
    // Add formatter column and migrate from writer to formatter.
    // The formatter field stores the Formatter alias (e.g., 'csv') while
    // writer stored the full Writer class name.
    $sql = <<<'SQL'
        ALTER TABLE `bulk_exporter`
            ADD `formatter` VARCHAR(190) DEFAULT NULL AFTER `label`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Column may already exist.
    }

    // Migrate writer class names to formatter aliases.
    $writerToFormatter = [
        'BulkExport\\Writer\\CsvWriter' => 'csv',
        'BulkExport\\Writer\\TsvWriter' => 'tsv',
        'BulkExport\\Writer\\TextWriter' => 'txt',
        'BulkExport\\Writer\\OpenDocumentSpreadsheetWriter' => 'ods',
        'BulkExport\\Writer\\OpenDocumentTextWriter' => 'odt',
        'BulkExport\\Writer\\JsonTableWriter' => 'json-table',
        'BulkExport\\Writer\\GeoJsonWriter' => 'geojson',
    ];

    foreach ($writerToFormatter as $writerClass => $formatterAlias) {
        $sql = <<<'SQL'
            UPDATE `bulk_exporter`
            SET `formatter` = :formatter
            WHERE `writer` = :writer AND (`formatter` IS NULL OR `formatter` = '');
            SQL;
        $connection->executeStatement($sql, [
            'formatter' => $formatterAlias,
            'writer' => $writerClass,
        ]);
    }

    $message = new PsrMessage(
        'The exporter architecture has been upgraded. Exporters now use Formatters directly for better performance and maintainability.' // @translate
    );
    $messenger->addSuccess($message);

    // Remove the legacy writer column now that all data has been migrated to formatter.
    $sql = <<<'SQL'
        ALTER TABLE `bulk_exporter`
            DROP COLUMN `writer`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Column may not exist or already removed.
    }

    // Rename 'writer' key to 'formatter' in config JSON (bulk_exporter.config).
    $sql = <<<'SQL'
        UPDATE `bulk_exporter`
        SET `config` = JSON_SET(
            JSON_REMOVE(`config`, '$.writer'),
            '$.formatter',
            JSON_EXTRACT(`config`, '$.writer')
        )
        WHERE JSON_EXTRACT(`config`, '$.writer') IS NOT NULL;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Fallback for older MySQL versions without JSON functions.
        $sql = "SELECT `id`, `config` FROM `bulk_exporter`";
        $stmt = $connection->executeQuery($sql);
        while ($row = $stmt->fetchAssociative()) {
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
        }
    }

    // Rename 'writer' key to 'formatter' in params JSON (bulk_export.params).
    $sql = <<<'SQL'
        UPDATE `bulk_export`
        SET `params` = JSON_SET(
            JSON_REMOVE(`params`, '$.writer'),
            '$.formatter',
            JSON_EXTRACT(`params`, '$.writer')
        )
        WHERE JSON_EXTRACT(`params`, '$.writer') IS NOT NULL;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Fallback for older MySQL versions without JSON functions.
        $sql = "SELECT `id`, `params` FROM `bulk_export`";
        $stmt = $connection->executeQuery($sql);
        while ($row = $stmt->fetchAssociative()) {
            $params = json_decode($row['params'], true) ?: [];
            if (isset($params['writer'])) {
                $params['formatter'] = $params['writer'];
                unset($params['writer']);
                $updateSql = "UPDATE `bulk_export` SET `params` = :params WHERE `id` = :id";
                $connection->executeStatement($updateSql, [
                    'params' => json_encode($params),
                    'id' => $row['id'],
                ]);
            }
        }
    }
}

// In all cases.

// Ensure value_data table exists (may have been missed if job failed).
$tableExists = $connection->executeQuery("SHOW TABLES LIKE 'value_data';")->fetchOne();
if (!$tableExists) {
    require_once dirname(__DIR__, 2) . '/src/Job/IndexValueLength.php';
    $dispatcher = $services->get('Omeka\Job\Dispatcher');
    $dispatcher->dispatch(\BulkExport\Job\IndexValueLength::class);
    $message = new PsrMessage(
        'The table to index value lengths was missing and has been recreated. A background job is populating it.' // @translate
    );
    $messenger->addWarning($message);
}

if (!empty($failExporters)) {
    try {
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        foreach (array_unique($failExporters) as $filepath) {
            // Fail: "EntityManager is closed".
            // $this->installExporter($filepath, true);
            $data = include $filepath;
            $sql = <<<'SQL'
                INSERT INTO `bulk_exporter`
                    (`owner_id`, `label`, `formatter`, `config`) VALUES
                    (:owner_id, :label, :formatter, :config)
                ON DUPLICATE KEY UPDATE
                    `owner_id` = :owner_id,
                    `label` = :label,
                    `formatter` = :formatter,
                    `config` = :config
                SQL;
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('owner_id', $user->getId(), \PDO::PARAM_INT);
            $stmt->bindValue('label', $data['label'], \PDO::PARAM_STR);
            // Support both new 'formatter' key and legacy 'writer' key.
            $stmt->bindValue('formatter', $data['formatter'] ?? $data['writer'] ?? null, \PDO::PARAM_STR);
            $stmt->bindValue('config', json_encode($data['config']), \PDO::PARAM_STR);
            $stmt->executeStatement();
        }
    } catch (\Exception $e) {
        // Nothing.
    }
}
