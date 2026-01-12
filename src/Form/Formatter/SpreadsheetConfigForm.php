<?php declare(strict_types=1);

namespace BulkExport\Form\Formatter;

use Common\Form\Element as CommonElement;
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
                        If "One value per column" is enabled, this separator is used only for non-property fields.
                        TXT, // @translate
                ],
                'attributes' => [
                    'id' => 'separator',
                    'value' => '|',
                ],
            ])
            ->add([
                'name' => 'value_per_column',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'One value per column', // @translate
                    'info' => <<<'TXT'
                        When enabled, each value of a multi-valued property gets its own column instead of being joined with a separator.
                        The module pre-scans all resources to determine the maximum number of values for each property.
                        For resources with fewer values, the extra columns are left empty.
                        TXT, // @translate
                ],
                'attributes' => [
                    'id' => 'value_per_column',
                ],
            ])
            ->add([
                'name' => 'column_metadata',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Column metadata in headers', // @translate
                    'info' => <<<'TXT'
                        Add metadata attributes to column headers.
                        When combined with "One value per column", creates separate columns for each metadata group.
                        Without "One value per column", values in each metadata group are joined with the separator.
                        TXT, // @translate
                    'value_options' => [
                        'language' => 'Language (e.g. dcterms:subject @fr)', // @translate
                        'datatype' => 'Datatype (e.g. dcterms:subject ^^uri)', // @translate
                        'visibility' => 'Visibility (e.g. dcterms:subject [private])', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'column_metadata',
                ],
            ])
        ;
        return $this;
    }
}
