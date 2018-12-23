<?php
namespace BulkImport\Form;

use BulkImport\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Element;
use Zend\Form\Form;

class CsvReaderConfigForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'delimiter',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Default delimiter', // @translate
            ],
            'attributes' => [
                'id' => 'delimiter',
                'value' => ',',
            ],
        ]);
        $this->add([
            'name' => 'enclosure',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Default enclosure', // @translate
            ],
            'attributes' => [
                'id' => 'enclosure',
                'value' => '"',
            ],
        ]);
        $this->add([
            'name' => 'escape',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Default escape', // @translate
            ],
            'attributes' => [
                'id' => 'escape',
                'value' => '\\',
            ],
        ]);
    }
}
