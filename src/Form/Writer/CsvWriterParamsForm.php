<?php
namespace BulkExport\Form\Writer;

use Zend\Form\Element;

class CsvWriterParamsForm extends CsvWriterConfigForm
{
    protected $writer;

    public function init()
    {
        // Set binary content encoding
        $this->setAttribute('enctype', 'multipart/form-data');

        $this->add([
            'name' => 'file',
            'type' => Element\File::class,
            'options' => [
                'label' => 'CSV file', // @translate
            ],
            'attributes' => [
                'id' => 'file',
                'required' => true,
            ],
        ]);

        parent::init();
    }
}
