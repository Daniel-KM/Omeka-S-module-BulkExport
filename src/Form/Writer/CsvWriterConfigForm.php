<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Laminas\Form\Element;

class CsvWriterConfigForm extends SpreadsheetWriterConfigForm
{
    public function init()
    {
        $this
            ->add([
                'name' => 'delimiter',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Delimiter', // @translate
                ],
                'attributes' => [
                    'id' => 'delimiter',
                    'value' => ',',
                ],
            ])
            ->add([
                'name' => 'enclosure',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Enclosure', // @translate
                ],
                'attributes' => [
                    'id' => 'enclosure',
                    'value' => '"',
                ],
            ])
            ->add([
                'name' => 'escape',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Escape', // @translate
                ],
                'attributes' => [
                    'id' => 'escape',
                    'value' => '\\',
                ],
            ]);

        parent::init();

        return $this;
    }
}
