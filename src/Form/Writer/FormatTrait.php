<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Laminas\Form\Element;
use Omeka\Form\Element as OmekaElement;

trait FormatTrait
{
    public function appendFormats()
    {
        $this
            ->add([
                'name' => 'format_fields',
                'type' => Element\Radio::class,
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
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Format of values', // @translate
                    'value_options' => [
                        'string' => 'String', // @translate
                        'html' => 'Html (may contain output of modules)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'format_generic',
                    'value' => 'string',
                ],
            ])
            ->add([
                'name' => 'format_resource',
                'type' => Element\Radio::class,
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
                'type' => OmekaElement\PropertySelect::class,
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
                'type' => Element\Radio::class,
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

    protected function addInputFilterFormats()
    {
        $this->getInputFilter()
            ->add([
                'name' => 'format_fields',
                'required' => false,
            ])
            ->add([
                'name' => 'format_generic',
                'required' => false,
            ])
            ->add([
                'name' => 'format_resource',
                'required' => false,
            ])
            ->add([
                'name' => 'format_resource_property',
                'required' => false,
            ])
            ->add([
                'name' => 'format_uri',
                'required' => false,
            ])
        ;
        return $this;
    }
}
