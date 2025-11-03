<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;

trait FormatTrait
{
    public function appendFormats()
    {
        $this
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
