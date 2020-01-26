<?php
namespace BulkExport\Form\Writer;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Element;
use Zend\Form\Form;

class AbstractWriterConfigForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $this
            ->add([
                'name' => 'comment',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Comment', // @translate
                    'info' => 'This optional comment will help admins for future reference.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment',
                    'value' => '',
                    'placeholder' => 'Optional comment for future reference.', // @translate
                ],
            ])
        ;
    }
}
