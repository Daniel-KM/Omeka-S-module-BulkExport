<?php
namespace CSVImportTest\Mvc\Controller\Plugin;

use CSVImport\Mvc\Controller\Plugin\AutomapHeadersToMetadata;
use OmekaTestHelper\Controller\OmekaControllerTestCase;

class AutomapHeadersToMetadataTest extends OmekaControllerTestCase
{
    protected $automapHeadersToMetadata;

    public function setUp()
    {
        parent::setup();

        $config = $serviceLocator->get('Config');
        $plugin = new AutomapHeadersToMetadata();
        $plugin->setConfigCsvImport($config['csvimport']);
        $this->automapHeadersToMetadata = $plugin;

        $this->loginAsAdmin();
    }
}
