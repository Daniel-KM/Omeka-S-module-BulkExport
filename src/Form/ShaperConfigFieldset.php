<?php declare(strict_types=1);

namespace BulkExport\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Api\Manager as ApiManager;

/**
 * Adapted:
 * @see \BulkEdit\Form\BulkEditFieldset
 * @see \BulkExport\Form\ShaperConfigFieldset
 * @see \SearchSolr\Form\Admin\SolrMapForm
 */
class ShaperConfigFieldset extends Fieldset
{
    use Formatter\FormatTrait;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $apiManager;

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

            /** @see \SearchSolr\Form\Admin\SolrMapForm */

            // TODO Filters.

            /* // The separator cannot be set for now, since there is no way to make it different for each cell.
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
            */

            ->appendFormats()
            ->remove('format_fields')
            ->remove('format_fields_labels')
            ->remove('language')

            ->add([
                'name' => 'normalization',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Cleaning and normalization', // @translate
                    'info' => 'The cleaning is processed in the following order.', // @translate'
                    'value_options' => [
                        'html_escaped' => 'Escape html', // @translate
                        'strip_tags' => 'Strip tags', // @translate
                        'lowercase' => 'Lower case', // @translate
                        'uppercase' => 'Upper case', // @translate
                        'ucfirst' => 'Upper case first character', // @translate
                        'remove_diacritics' => 'Remove diacritics', // @translate
                        'alphanumeric' => 'Alphanumeric only', // @translate
                        'alphabetic' => 'Alphabetic only', // @translate
                        'max_length' => 'Max length', // @translate
                        'integer' => 'Number', // @translate
                        'year' => 'Year', // @translate
                        'table' => 'Map value to a code or code to a value (module Table)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'normalization',
                    'value' => [
                    ],
                    // Is used with all values.
                    // 'data-formatter' => 'text',
                ],
            ])
            ->add([
                'name' => 'max_length',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Max length', // @translate
                ],
                'attributes' => [
                    'id' => 'max_length',
                    // Setting for normalization "max_length" only.
                    'data-normalization' => 'max_length',
                ],
            ])

            ->add([
                'name' => 'prepend',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'String to prepend', // @translate
                ],
                'attributes' => [
                    'id' => 'replace_prepend',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
            ->add([
                'name' => 'append',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'String to append', // @translate
                ],
                'attributes' => [
                    'id' => 'replace_append',
                    // This attribute is required to make "batch edit all" working.
                    'data-collection-action' => 'replace',
                ],
            ])
        ;

            if (class_exists('Table\Module', false)) {
                $this
                    ->add([
                        'name' => 'table',
                        'type' => \Table\Form\Element\TablesSelect::class,
                        'options' => [
                            'label' => 'Table for normalization "Table"', // @translate
                            'disable_group_by_owner' => true,
                            'empty_option' => '',
                        ],
                        'attributes' => [
                            'id' => 'table',
                            'class' => 'chosen-select',
                            'required' => false,
                            'data-placeholder' => 'Select a table…', // @translate
                            'value' => '',
                            // Setting for normalization "table" only.
                            'data-normalization' => 'table',
                        ],
                    ])
                    ->add([
                        'name' => 'table_mode',
                        'type' => Element\Radio::class,
                        'options' => [
                            'label' => 'Table: Mode of normalization', // @translate
                            'value_options' => [
                                'label' => 'Label only', // @translate
                                'code' => 'Code only', // @translate
                                // TODO Manage multiple outputs in ShaperTrait.
                                // 'both' => 'Label and code', // @translate
                            ],
                        ],
                        'attributes' => [
                            'id' => 'table_mode',
                            'required' => false,
                            'value' => 'label',
                            'data-normalization' => 'table',
                        ],
                    ])
                    ->add([
                        'name' => 'table_index_original',
                        'type' => Element\Checkbox::class,
                        'options' => [
                            'label' => 'Table: include original value too', // @translate
                        ],
                        'attributes' => [
                            'id' => 'table_index_original',
                            'required' => false,
                            'data-normalization' => 'table',
                        ],
                    ])
                    ->add([
                        'name' => 'table_check_strict',
                        'type' => Element\Checkbox::class,
                        'options' => [
                            'label' => 'Table: strict check (same case, same diacritics)', // @translate
                        ],
                        'attributes' => [
                            'id' => 'table_check_strict',
                            'required' => false,
                            'data-normalization' => 'table',
                        ],
                    ]);

                // TODO Why the fieldset does not use form manager to load and init form element?
                $this->get('table')
                    ->setApiManager($this->apiManager);
            }
    }

    public function setApiManager(ApiManager $apiManager): self
    {
        $this->apiManager = $apiManager;
        return $this;
    }
}
