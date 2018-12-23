<?php
namespace Import\Form;

use Import\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Form;

class CsvReaderConfigForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'delimiter',
            'type' => 'text',
            'options' => [
                'label' => 'Default delimiter', // @translate
            ],
            'attributes' => [
                'value' => ',',
            ],
        ]);
        $this->add([
            'name' => 'enclosure',
            'type' => 'text',
            'options' => [
                'label' => 'Default enclosure', // @translate
            ],
            'attributes' => [
                'value' => '"',
            ],
        ]);
        $this->add([
            'name' => 'escape',
            'type' => 'text',
            'options' => [
                'label' => 'Default escape', // @translate
            ],
            'attributes' => [
                'value' => '\\',
            ],
        ]);
    }
}