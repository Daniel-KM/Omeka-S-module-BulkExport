<?php declare(strict_types=1);

namespace BulkExportTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class BulkExportControllerTestCase extends OmekaControllerTestCase
{
    protected function getSettings()
    {
        return [];
    }

    public function setUp(): void
    {
        $this->loginAsAdmin();
    }

    public function tearDown(): void
    {
    }
}
