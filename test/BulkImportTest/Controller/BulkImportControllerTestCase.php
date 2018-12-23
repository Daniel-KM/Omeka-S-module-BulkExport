<?php

namespace BulkImportTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class BulkImportControllerTestCase extends OmekaControllerTestCase
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
