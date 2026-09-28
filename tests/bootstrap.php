<?php declare(strict_types=1);

/**
 * Bootstrap file for module tests.
 *
 * @see \CommonTest\Bootstrap
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        'Log',
        'BulkExport',
        // Optional: registered globally via STI on resource, so its table must
        // exist for resource value queries (linked resources).
        '?DigitalObject',
        // Optional: export of the persistent identifiers.
        '?PersistentIdentifiers',
        // Optional: export of the last operation.
        '?HistoryLog',
    ],
    'BulkExportTest',
    __DIR__ . '/BulkExportTest'
);
