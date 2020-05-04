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
            ->add([
                'name' => 'use_background',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Use a background job', // @translate
                    'info' => ' For complex formats, the process may require more than 30 seconds, that is the default duration of web server before error.', // @translate
                ],
                'attributes' => [
                    'id' => 'use_background',
                    'checked' => true,
                ],
            ])
        ;
        return $this;
    }
}
