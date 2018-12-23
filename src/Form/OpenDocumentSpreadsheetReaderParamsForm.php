<?php
namespace BulkImport\Form;

use Zend\Form\Element;

class OpenDocumentSpreadsheetReaderParamsForm extends SpreadsheetReaderConfigForm
{
    protected $reader;

    public function init()
    {
        // Set binary content encoding
        $this->setAttribute('enctype', 'multipart/form-data');

        $this->add([
            'name' => 'file',
            'type' => Element\File::class,
            'options' => [
                'label' => 'OpenDocument Spreadsheet (ods)', // @translate
            ],
            'attributes' => [
                'id' => 'file',
                'required' => true,
            ],
        ]);

        parent::init();
    }
}
