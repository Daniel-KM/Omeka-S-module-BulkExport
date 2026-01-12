<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Omeka\Form\Element as OmekaElement;

trait FormatTrait
{
    public function appendFormats()
    {
        $this
            ->add([
                'name' => 'metadata_shapers',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Specific shapers by metadata', // @translate
                    'info' => 'Shapers are defined in the page "Shapers" and allows to define specific rules for specific metadata. If not set, the rules are the main ones. Set the metadata name, "=" and the identifier or label of the shaper.', // @translate
                    'as_key_value' => true,
                ],
                'attributes' => [
                    'id' => 'metadata_shaper',
                    'rows' => '10',
                    'placeholder' => <<<'TXT'
                        dcterms:creator = Person
                        dcterms:date = Year only
                        TXT, // @translate
                ],
            ])
            ->add([
                'name' => 'format_fields',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Metadata names or headers', // @translate
                    'value_options' => [
                        'name' => 'Rdf names', // @translate
                        'label' => 'Labels', // @translate
                        'template' => 'First template alternative name', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'format_fields',
                    'value' => 'name',
                ],
            ])
            ->add([
                'name' => 'format_fields_labels',
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
                    'id' => 'format_fields_labels',
                    'rows' => '10',
                    'placeholder' => <<<'TXT'
                        Person = dcterms:creator dcterms:contributor
                        dcterms:subject = dcterms:subject dcterms:temporal
                        TXT, // @translate
                ],
            ])
            ->add([
                'name' => 'format_generic',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Format of values', // @translate
                    'value_options' => [
                        'string' => 'String', // @translate
                        'html' => 'Html', // @translate
                        // TODO Add output for html with event for modules? Probably useless.
                        // 'html_modules' => 'Html (with output of modules)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'format_generic',
                    'value' => 'string',
                ],
            ])
            ->add([
                'name' => 'format_resource',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Format of linked resources', // @translate
                    'value_options' => [
                        'identifier' => 'Identifier (property below)', // @translate
                        'id' => 'Id', // @translate
                        'identifier_id' => 'Identifier or id', // @translate
                        'url' => 'Omeka url', // @translate
                        'title' => 'Title', // @translate
                        'url_title' => 'Omeka url and title', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'format_resource',
                    'value' => 'id',
                ],
            ])
            ->add([
                'name' => 'format_resource_property',
                'type' => CommonElement\OptionalPropertySelect::class,
                'options' => [
                    'label' => 'Property for linked resources', // @translate
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'format_resource_property',
                    'multiple' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select property…', // @translate
                    'value' => 'dcterms:identifier',
                ],
            ])
            ->add([
                'name' => 'format_uri',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
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
                    'id' => 'format_uri',
                    'value' => 'uri_label',
                ],
            ])
            ->add([
                'name' => 'language',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Language', // @translate
                ],
                'attributes' => [
                    'id' => 'language',
                ],
            ])
        ;
        return $this;
    }
}
