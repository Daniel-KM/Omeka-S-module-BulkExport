<?php declare(strict_types=1);

namespace BulkExport\Form\Formatter;

use Laminas\Form\Element;

class SpreadsheetConfigForm extends FieldsConfigForm
{
    public function appendSpecific(): self
    {
        $this
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
        ;
        return $this;
    }
}
