<?php

namespace CSVImportTest\Controller;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class CsvImportControllerTestCase extends OmekaControllerTestCase
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
