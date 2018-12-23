<?php
namespace BulkImport;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.0.1', '<')) {
    $this->checkDependency();

    /** @var \Omeka\Module\Manager $moduleManager */
    $moduleManager = $services->get('Omeka\ModuleManager');
    $module = $moduleManager->getModule('Log');
    $version = $module->getDb('version');
    if (version_compare($version, '3.2.2', '<')) {
        throw new \Omeka\Module\Exception\ModuleCannotInstallException(
            'BulkImport requires module Log version 3.2.2 or higher.' // @translate
        );
    }

    $sql = <<<'SQL'
ALTER TABLE bulk_log DROP FOREIGN KEY FK_3B78A07DB6A263D9;
DROP TABLE bulk_log;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.0.2', '<')) {
    $sql = <<<'SQL'
ALTER TABLE bulk_import
    ADD job_id INT DEFAULT NULL AFTER importer_id,
    DROP status,
    DROP started,
    DROP ended,
    CHANGE importer_id importer_id INT DEFAULT NULL,
    CHANGE reader_params reader_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    CHANGE processor_params processor_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
ALTER TABLE bulk_import
    ADD CONSTRAINT FK_BD98E874BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id);
CREATE UNIQUE INDEX UNIQ_BD98E874BE04EA9 ON bulk_import (job_id);
ALTER TABLE bulk_importer
    CHANGE name name VARCHAR(190) DEFAULT NULL,
    CHANGE reader_name reader_name VARCHAR(190) DEFAULT NULL,
    CHANGE reader_config reader_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    CHANGE processor_name processor_name VARCHAR(190) DEFAULT NULL,
    CHANGE processor_config processor_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.0.3', '<')) {
    $sql = <<<'SQL'
ALTER TABLE bulk_import
    CHANGE importer_id importer_id INT DEFAULT NULL,
    CHANGE job_id job_id INT DEFAULT NULL,
    CHANGE reader_params reader_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    CHANGE processor_params processor_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
ALTER TABLE bulk_importer
    ADD owner_id INT DEFAULT NULL AFTER id,
    CHANGE name `label` VARCHAR(190) DEFAULT NULL,
    CHANGE reader_name reader_class VARCHAR(190) DEFAULT NULL,
    CHANGE processor_name processor_class VARCHAR(190) DEFAULT NULL,
    CHANGE reader_config reader_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    CHANGE processor_config processor_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
ALTER TABLE bulk_importer
    ADD CONSTRAINT FK_2DAF62D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL;
CREATE INDEX IDX_2DAF62D7E3C61F9 ON bulk_importer (owner_id);
SQL;
    $connection->exec($sql);
}
