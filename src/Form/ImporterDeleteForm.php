<?php
namespace Import\Form;

use Import\Entity\Importer;

use Zend\Form\Form;

class ImporterDeleteForm extends Form
{
    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'importer_submit',
            'type' => 'fieldset',
        ]);
        $this->get('importer_submit')->add([
            'type'  => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Delete importer',
                'id' => 'submitbutton',
            ],
        ]);
    }
}
