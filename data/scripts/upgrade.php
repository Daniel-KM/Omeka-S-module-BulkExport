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
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.57')) {
    $message = new \Omeka\Stdlib\Message(
        'The module %1$s should be upgraded to version %2$s or later.', // @translate
        'Common', '3.4.57'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

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
    CHANGE `writer_config` `config` LONGTEXT NOT NULL COMMENT '(DC2Type:json)' AFTER `writer`
;
ALTER TABLE `bulk_export`
    CHANGE `writer_params` `params` LONGTEXT NOT NULL COMMENT '(DC2Type:json)' AFTER `comment`,
    CHANGE `filename` `filename` VARCHAR(760) DEFAULT NULL AFTER `params`
;

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
    $connection->executeStatement(str_replace(array_keys($replace), array_values($replace), $sql));

    $message = new PsrMessage(
        'The config of exporters has been upgraded to a new format. You may check them if you use a complex config.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.31', '<')) {
    $sqls = <<<'SQL'
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
}
