<?php declare(strict_types=1);

namespace BulkExport\Form;

use BulkExport\Form\Writer\MetadataSelectTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    use MetadataSelectTrait;

    /**
     * @var string
     */
    protected $label = 'Bulk Export'; // @translate

    protected $elementGroups = [
        'export' => 'Bulk Export', // @translate
    ];

    protected $formatters = [];

    public function init(): void
    {
        // TODO Use a main shaper for default config, then use it here instead of the full list of params.

        $this
            ->setAttribute('id', 'bulk-export')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'bulkexport_limit',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Maximum number of resources to export', // @translate
                    'info' => 'This setting is applied only for the direct output.', // @translate
                ],
                'attributes' => [
                    'id' => 'bulkexport_limit',
                    'min' => 0,
                ],
            ])

            ->addDisplayViews()

            ->add([
                'name' => 'bulkexport_formatters',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Formatters to display in resource pages', // @translate
                    'value_options' => $this->formatters,
                ],
                'attributes' => [
                    'id' => 'bulkexport_formatters',
                ],
            ])

            ->appendMetadataSelect('bulkexport_')

            ->add([
                'name' => 'bulkexport_metadata_shapers',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Specific shapers by metadata', // @translate
                    'info' => 'Shapers are defined in the page "Shapers" and allows to define specific rules for specific metadata. If not set, the rules are the main ones. Set the metadata name, "=" and the identifier or label of the shaper.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'bulkexport_metadata_shaper',
                    'rows' => '10',
                    'placeholder' => <<<'TXT'
                        dcterms:creator = Person
                        dcterms:date = Year only
                        TXT, // @translate
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_fields',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Metadata names or headers', // @translate
                    'value_options' => [
                        'name' => 'Rdf names', // @translate
                        'label' => 'Labels', // @translate
                        'template' => 'First template alternative name', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_fields',
                    'value' => 'name',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_fields_labels',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Labels with field names for merging and specific order', // @translate
                    'info' => 'Fill the label and the field names to manage a specific order of data and merge of metadata. Unlisted metadata will be added next.', // @translate
                    // Do not export as key value in order to allow multiple
                    // columns with the same header.
                    'as_key_value' => false,
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_fields_labels',
                    'rows' => '10',
                    'placeholder' => <<<'TXT'
                        Person = dcterms:creator dcterms:contributor
                        dcterms:subject = dcterms:subject dcterms:temporal
                        TXT, // @translate
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_generic',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Format of values', // @translate
                    'value_options' => [
                        'string' => 'String', // @translate
                        'html' => 'Html (slow with some modules)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_generic',
                    'value' => 'string',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_resource',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Format of linked resources', // @translate
                    'value_options' => [
                        'id' => 'Id', // @translate
                        'identifier' => 'Identifier (property below)', // @translate
                        'identifier_id' => 'Identifier or id', // @translate
                        'url_title' => 'Omeka url and title', // @translate
                        'title' => 'Title', // @translate
                        'url' => 'Omeka url', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_resource',
                    'value' => 'id',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_resource_property',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Property for linked resources', // @translate
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_resource_property',
                    'multiple' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select property…', // @translate
                    'value' => 'dcterms:identifier',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_uri',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Format of uri', // @translate
                    'value_options' => [
                        'uri_label' => 'Uri and label separated by a space', // @translate
                        'uri' => 'Uri only', // @translate
                        'label_uri' => 'Label if any, else uri', // @translate
                        'label' => 'Label only', // @translate
                        'html' => 'Html', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_uri',
                    'value' => 'uri_label',
                ],
            ])
            ->add([
                'name' => 'bulkexport_language',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Language', // @translate
                ],
                'attributes' => [
                    'id' => 'bulkexport_language',
                ],
            ])
        ;
    }

    protected function addDisplayViews(): self
    {
        return $this;
    }

    public function setFormatters(array $formatters): self
    {
        $this->formatters = $formatters;
        return $this;
    }
}
