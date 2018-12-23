<?php
namespace BulkImport\Form;

use Zend\Form\Element;

class CsvReaderConfigForm extends SpreadsheetReaderConfigForm
{
    public function init()
    {
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

        parent::init();
    }
}
