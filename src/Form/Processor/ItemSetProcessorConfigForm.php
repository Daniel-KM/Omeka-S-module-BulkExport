<?php
namespace BulkImport\Form\Processor;

use Zend\Form\Element;

class ItemSetProcessorConfigForm extends AbstractResourceProcessorConfigForm
{
    protected function addFieldsets()
    {
        parent::addFieldsets();

        $this->add([
            'name' => 'o:is_open',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Openness', // @translate
                'value_options' => [
                    'true' => 'Open', // @translate
                    'false' => 'Close', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'o-is-open',
            ],
        ]);
    }

    protected function addInputFilter()
    {
        parent::addInputFilter();

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:is_open',
            'required' => false,
        ]);
    }
}
