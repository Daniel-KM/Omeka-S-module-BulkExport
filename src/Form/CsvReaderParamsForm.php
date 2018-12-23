<?php
namespace Import\Form;

class CsvReaderParamsForm extends CsvReaderConfigForm
{
    protected $reader;

    public function init()
    {
        // Set binary content encoding
        $this->setAttribute('enctype', 'multipart/form-data');

        $this->add([
            'name' => 'file',
            'type' => 'file',
            'options' => [
                'label' => 'CSV file', // @translate
            ],
        ]);

        parent::init();
    }
}
