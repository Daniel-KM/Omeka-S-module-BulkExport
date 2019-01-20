<?php
namespace BulkExportTest\Mvc\Controller\Plugin;

use BulkExport\Mvc\Controller\Plugin\AutomapFields;
use OmekaTestHelper\Controller\OmekaControllerTestCase;

class AutomapFieldsTest extends OmekaControllerTestCase
{
    protected $automapFields;

    public function setUp()
    {
        parent::setup();

        $services = $this->getServiceLocator();

        // Copy of the factory of the plugin.
        $filepath = '/data/mappings/fields_to_metadata.php';
        $map = require dirname(dirname(dirname(dirname(__DIR__)))) . $filepath;
        $viewHelpers = $services->get('ViewHelperManager');
        return new AutomapFields(
            $map,
            $viewHelpers->get('api'),
            $viewHelpers->get('translate')
        );

        $this->loginAsAdmin();
    }
}
