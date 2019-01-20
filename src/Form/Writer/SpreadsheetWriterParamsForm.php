<?php
namespace BulkExport\Form\Writer;

use Zend\Form\Element;

class SpreadsheetWriterParamsForm extends CsvWriterConfigForm
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
                'label' => 'Spreadsheet (csv, tsv, OpenDocument ods)', // @translate
            ],
            'attributes' => [
                'id' => 'file',
                'required' => true,
            ],
        ]);

        parent::init();
    }
}
