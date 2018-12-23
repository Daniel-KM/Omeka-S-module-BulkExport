<?php
namespace Import\Form;

use Import\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class ImporterStartForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        $this->add([
            'name' => 'start_submit',
            'type' => Fieldset::class,
        ]);

        $fieldset = $this->get('start_submit');

        $fieldset->add([
            'type'  => Element\Submit::class,
            'name' => 'submit',
            'attributes' => [
                'value' => 'Start import', // @translate
            ],
        ]);
    }
}
