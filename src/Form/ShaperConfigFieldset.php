<?php declare(strict_types=1);

namespace BulkExport\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class ShaperConfigFieldset extends Fieldset
{
    use Writer\FormatTrait;

    public function init(): void
    {
        $this
            ->setAttribute('id', 'bulk-shaper-config-fieldset')

            ->add([
                'name' => 'comment',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Comment', // @translate
                    'info' => 'This optional description will help to understand the purpose of this shaper.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment',
                    'value' => '',
                    'placeholder' => 'This shaper can be use to…', // @translate
                ],
            ])

            ->add([
                'name' => 'separator',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Multi-value separator', // @translate
                    'info' => <<<'TXT'
                        To output all values of each property, cells can be multivalued with this separator.
                        It is recommended to use a character that is never used, like "|", or a random string.
                        TXT, // @translate
                ],
                'attributes' => [
                    'id' => 'separator',
                    'value' => '|',
                ],
            ])

            ->appendFormats()
            ->remove('language')
        ;
    }
}
