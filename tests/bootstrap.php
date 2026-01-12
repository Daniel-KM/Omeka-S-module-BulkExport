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
    ],
    'BulkExportTest',
    __DIR__ . '/BulkExportTest'
);
