<?php
namespace BulkExport\Form\Writer;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Element;
use Zend\Form\Form;

class SpreadsheetWriterConfigForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'separator',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Multi-value separator', // @translate
                'info' => 'If cells are multivalued, it is recommended to use a character that is never used, like "|" or a random string.', // @translate
            ],
            'attributes' => [
                'id' => 'separator',
                'value' => '',
            ],
        ]);
    }
}