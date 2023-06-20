<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Laminas\Form\Element;
use Laminas\Form\Form;

abstract class AbstractWriterConfigForm extends Form
{
    protected function appendBase(): self
    {
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
                    'info' => 'For complex formats or numerous resources, the process may require more than 30 seconds, that is the default duration of web server before error.', // @translate
                ],
                'attributes' => [
                    'id' => 'use_background',
                    'checked' => true,
                ],
            ])
        ;
        return $this;
    }

    protected function appendFile(): self
    {
        $this
            ->add([
                'name' => 'filename',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Filename', // @translate
                    'info' => 'This setting allows to store the file always with the same name, generally for server tasks. The existing file will be overridden. Available placeholders are: "{label}", "{exporter}", "{date}", "{time}", "{userid}", "{username}.', // @translate
                ],
                'attributes' => [
                    'id' => 'filename',
                    'placeholder' => '{label]-{date}-{time}',
                ],
            ])
        ;

        return $this;
    }
}
