<?php
namespace BulkImport\Form\Reader;

use Zend\Form\Element;

class CsvReaderConfigForm extends SpreadsheetReaderConfigForm
{
    public function init()
    {
        $this->add([
            'name' => 'delimiter',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Delimiter', // @translate
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
                'label' => 'Enclosure', // @translate
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
                'label' => 'Escape', // @translate
            ],
            'attributes' => [
                'id' => 'escape',
                'value' => '\\',
            ],
        ]);

        parent::init();
    }
}
