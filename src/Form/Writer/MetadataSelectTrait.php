<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Omeka\Form\Element as OmekaElement;

trait MetadataSelectTrait
{
    public function appendMetadataSelect($name = 'metadata')
    {
        $this
            ->add([
                'name' => $name,
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Metadata', // @translate
                    'info' => 'If empty, all used properties will be returned.', // @translate
                    'term_as_value' => true,
                    'prepend_value_options' => $this->prependMappingOptions(),
                ],
                'attributes' => [
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select one or more metadata…', // @translate
                ],
            ])
            ->add([
                'name' => $name . '_exclude',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Metadata to exclude', // @translate
                    'info' => 'It is recommended to remove big fields from the list of properties, in particular extracted text.', // @translate
                    'term_as_value' => true,
                    'prepend_value_options' => [
                        'metadata' => [
                            'label' => 'Resource metadata', // @translate
                            'options' => [
                                'properties_min_500' => 'All used properties more than 500 characters', // @translate
                                'properties_min_1000' => 'All used properties more than 1000 characters', // @translate
                                'properties_min_5000' => 'All used properties more than 5000 characters', // @translate
                            ],
                        ],
                    ],
                ],
                'attributes' => [
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select one or more metadata…', // @translate
                ],
            ]);
        return $this;
    }

    protected function addInputFilterMetadata($name = 'metadata')
    {
        $this->getInputFilter()
            ->add([
                'name' => $name,
                'required' => false,
            ])
            ->add([
                'name' => $name . '_exclude',
                'required' => false,
            ]);
        return $this;
    }

    protected function prependMappingOptions()
    {
        $mapping = [
            'metadata' => [
                'label' => 'Resource metadata', // @translate
                'options' => [
                    'o:id' => 'Internal id', // @translate
                    'url' => 'Resource url', // @translate
                    // The resource type is the @type, but it may be the api
                    // resource id (the name).
                    'resource_type' => 'Resource type', // @translate
                    'o:resource_template' => 'Resource template', // @translate
                    'o:resource_class' => 'Resource class', // @translate
                    'o:owner' => 'Owner email', // @translate
                    'o:owner/o:id' => 'Owner id', // @translate
                    'o:is_public' => 'Visibility public/private', // @translate
                    // For item set.
                    'o:is_open' => 'Openness', // @translate
                    // For all resources.
                    'properties' => 'All used properties', // @translate
                    'properties_max_500' => 'All used properties less than 500 characters', // @translate
                    'properties_min_500' => 'All used properties more than 500 characters', // @translate
                    'properties_max_1000' => 'All used properties less than 1000 characters', // @translate
                    'properties_min_1000' => 'All used properties more than 1000 characters', // @translate
                    'properties_max_5000' => 'All used properties less than 5000 characters', // @translate
                    'properties_min_5000' => 'All used properties more than 5000 characters', // @translate
                    // Modules.
                    // 'o-history-log:event' => 'History log events (module History Log)',
                    'operation' => 'History log last operation (create, update, delete or undelete)',
                    // Folksonomy.
                    'o-module-folksonomy:tag' => 'Tags (Folksonomy)', // @translate
                ],
            ],
            'o:item_set' => [
                'label' => 'Item set (for item)', // @translate
                'options' => [
                    'o:item_set/o:id' => 'Internal id', // @translate
                    'o:item_set/dcterms:identifier' => 'Identifier', // @translate
                    'o:item_set/dcterms:title' => 'Label (first title)', // @translate
                ],
            ],
            'o:media' => [
                'label' => 'Media (for item)', // @translate
                'options' => [
                    'o:media/o:id' => 'Internal id', // @translate
                    'o:media/file' => 'Url / File', // @translate
                    'o:media/o:source' => 'Source url / file', // @translate
                    'o:media/dcterms:identifier' => 'Identifier', // @translate
                    'o:media/dcterms:title' => 'Label (first title)', // @translate
                ],
            ],
            'o:item' => [
                'label' => 'Item (for media)', // @translate
                'options' => [
                    'o:item/o:id' => 'Internal id', // @translate
                    'o:item/dcterms:identifier' => 'Identifier', // @translate
                    'o:item/dcterms:title' => 'Label (first title)', // @translate
                ],
            ],
            'o:annotation' => [
                'label' => 'Resource (for annotation)', // @translate
                'options' => [
                    'o:resource/o:id' => 'Internal id', // @translate
                    'o:resource/dcterms:identifier' => 'Identifier', // @translate
                    'o:resource/dcterms:title' => 'Label (first title)', // @translate
                ],
            ],
        ];

        if (!class_exists(\Annotate\Entity\Annotation::class)) {
            unset($mapping['o:annotation']);
        }

        return $mapping;
    }
}
