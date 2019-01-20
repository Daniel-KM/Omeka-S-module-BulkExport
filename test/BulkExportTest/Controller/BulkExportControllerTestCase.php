<?php

namespace BulkExportTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class BulkExportControllerTestCase extends OmekaControllerTestCase
{
    protected function getSettings()
    {
        return [];
    }

    public function setUp()
    {
        $this->loginAsAdmin();
    }

    public function tearDown()
    {
    }
}
