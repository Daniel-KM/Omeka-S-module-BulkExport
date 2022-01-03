<?php declare(strict_types=1);

namespace BulkExport;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
// $settings = $services->get('Omeka\Settings');
// $config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
// $space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.0.7', '<')) {
    $directory = new \RecursiveDirectoryIterator(dirname(__DIR__) . '/exporters', \RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new \RecursiveIteratorIterator($directory);
    foreach (array_keys($iterator) as $filepath) {
        $this->installExporter($filepath);
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
    $filepaths = [
        dirname(__DIR__) . '/exporters/txt.php',
        dirname(__DIR__) . '/exporters/odt.php',
    ];
    foreach ($filepaths as $filepath) {
        $this->installExporter($filepath);
    }
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

if (version_compare($oldVersion, '3.3.12.13', '<')) {
    // TODO Re-enable the check when patch https://github.com/omeka-s-modules/CSVImport/pull/182 will be included.
    /*
    if (class_exists(\Box\Spout\Reader\ReaderFactory::class)) {
        $message = 'The dependency Box/Spout version should be >= 3.0. See readme.'; // @translate
        throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
    }
    */
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
