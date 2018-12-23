<?php

namespace BulkImport;

use BulkImport\Processor\ItemsProcessor;
use BulkImport\Reader\CsvReader;
use Omeka\Module\AbstractModule;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = <<<SQL
CREATE TABLE bulk_import (
    id INT AUTO_INCREMENT NOT NULL,
    importer_id INT DEFAULT NULL,
    reader_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)',
    processor_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)',
    status VARCHAR(255) DEFAULT NULL,
    started DATETIME DEFAULT NULL,
    ended DATETIME DEFAULT NULL,
    INDEX IDX_BD98E8747FCFE58E (importer_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE bulk_importer (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    reader_name VARCHAR(255) DEFAULT NULL,
    reader_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)',
    processor_name VARCHAR(255) DEFAULT NULL,
    processor_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)',
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE bulk_log (
    id INT AUTO_INCREMENT NOT NULL,
    import_id INT NOT NULL,
    severity VARCHAR(255) DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)',
    added DATETIME DEFAULT NULL,
    INDEX IDX_3B78A07DB6A263D9 (import_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE bulk_import ADD CONSTRAINT FK_BD98E8747FCFE58E FOREIGN KEY (importer_id) REFERENCES bulk_importer (id);
ALTER TABLE bulk_log ADD CONSTRAINT FK_3B78A07DB6A263D9 FOREIGN KEY (import_id) REFERENCES bulk_import (id) ON DELETE CASCADE;
SQL;
        $connection->exec($sql);
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec('ALTER TABLE bulk_log DROP FOREIGN KEY FK_3B78A07DB6A263D9;');
        $connection->exec('ALTER TABLE bulk_import DROP FOREIGN KEY FK_BD98E8747FCFE58E;');
        $connection->exec('DROP TABLE bulk_log;');
        $connection->exec('DROP TABLE bulk_import;');
        $connection->exec('DROP TABLE bulk_importer;');
    }

    /**
     * Attach listeners to events.
     *
     * @param SharedEventManagerInterface $sharedEventManager
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            self::class,
            'readers',
            [$this, 'getReaders']
        );
        $sharedEventManager->attach(
            self::class,
            'processors',
            [$this, 'getProcessors']
        );
    }

    public function getReaders()
    {
        return [
            'csv' => CsvReader::class,
        ];
    }

    public function getProcessors()
    {
        return [
            'items' => ItemsProcessor::class,
        ];
    }
}
