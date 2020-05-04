<?php
namespace BulkExport\Form\Writer;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Element;

class SpreadsheetWriterConfigForm extends AbstractWriterConfigForm
{
    use MetadataSelectTrait;
    use ResourceTypesSelectTrait;
    use ResourceQueryTrait;
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'separator',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Multi-value separator', // @translate
                'info' => 'To output all values of each property, cells can be multivalued with this separator.
it is recommended to use a character that is never used, like "|", or a random string.', // @translate
            ],
            'attributes' => [
                'id' => 'separator',
                'value' => '',
            ],
        ]);

        $this->appendResourceTypesSelect();
        $this->appendMetadataSelect();

        $this->appendResourceQuery();

        $this->addInputFilterResourceTypes();
        $this->addInputFilterMetadata();
        return $this;
    }
}
