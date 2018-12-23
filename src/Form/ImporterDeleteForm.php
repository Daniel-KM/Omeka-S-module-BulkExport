<?php
namespace Import\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class ImporterDeleteForm extends Form
{
    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'importer_submit',
            'type' =>  Fieldset::class,
        ]);

        $fieldset = $this->get('importer_submit');

        $fieldset->add([
            'type'  => Element\Submit::class,
            'name' => 'submit',
            'attributes' => [
                'id' => 'submitbutton',
                'value' => 'Delete importer', // @translate
            ],
        ]);
    }
}
