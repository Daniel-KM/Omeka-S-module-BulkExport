<?php

namespace Import;

use Import\Processor\ItemsProcessor;
use Import\Reader\CsvReader;
use Omeka\Module\AbstractModule;
use Omeka\Entity\Job;
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
        $sql = "
            CREATE TABLE IF NOT EXISTS `import_importers` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `reader_name` varchar(255) NOT NULL,
                `reader_config` text NULL DEFAULT NULL,
                `processor_name` varchar(255) NOT NULL,
                `processor_config` text NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            
            CREATE TABLE IF NOT EXISTS `import_imports` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `importer_id` int unsigned NOT NULL,
                `reader_params` text NULL DEFAULT NULL,
                `processor_params` text NULL DEFAULT NULL,
                `status` varchar(255) NULL DEFAULT NULL,
                `started` timestamp NULL DEFAULT NULL,
                `ended` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY (`importer_id`),
                CONSTRAINT `import_imports_fk_importer_id`
                  FOREIGN KEY (`importer_id`) REFERENCES `import_importers` (`id`)
                  ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            
            CREATE TABLE IF NOT EXISTS `import_logs` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `import_id` int unsigned NOT NULL,
                `severity` int NOT NULL DEFAULT 0,
                `message` text NOT NULL,
                `params` text NULL DEFAULT NULL,
                `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY (`import_id`),
                CONSTRAINT import_logs_fk_import_id
                  FOREIGN KEY (`import_id`) REFERENCES `import_imports` (`id`)
                  ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ";
        $connection->exec($sql);
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec("ALTER TABLE import_logs DROP FOREIGN KEY import_logs_fk_import_id;");
        $connection->exec("ALTER TABLE import_imports DROP FOREIGN KEY import_imports_fk_importer_id;");
        $connection->exec("DROP TABLE import_logs;");
        $connection->exec("DROP TABLE import_imports;");
        $connection->exec("DROP TABLE import_importers;");
    }

    /**
     * Attach listeners to events.
     *
     * @param SharedEventManagerInterface $sharedEventManager
     */
    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(self::class, 'readers', array($this, 'getReaders'));
        $sharedEventManager->attach(self::class, 'processors', array($this, 'getProcessors'));
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
