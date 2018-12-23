<?php
namespace Import\Form;

use Import\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Form;

class ImporterStartForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        $this->add([
            'name' => 'start_submit',
            'type' => 'fieldset',
        ]);
        $this->get('start_submit')->add([
            'type'  => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Start import',
            ],
        ]);
    }
}
