<?php
namespace BulkExport\Form\Writer;

use Omeka\Form\Element\PropertySelect;

trait MetadataSelectTrait
{
    public function appendMetadataSelect()
    {
        $this->add([
            'name' => 'metadata',
            'type' => PropertySelect::class,
            'options' => [
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
        ]);
    }

    protected function addInputFilterMetadata()
    {
        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'metadata',
            'required' => false,
        ]);
    }

    protected function prependMappingOptions()
    {
        return [
            'metadata' => [
                'label' => 'Resource metadata', // @translate
                'options' => [
                    'o:id' => 'Internal id', // @translate
                    // The resource type is the @type, but it may be the api
                    // resource id (the name).
                    'resource_type' => 'Resource type', // @translate
                    'o:resource_template' => 'Resource template', // @translate
                    'o:resource_class' => 'Resource class', // @translate
                    'o:owner' => 'Owner email', // @translate
                    'o:owner[o:id]' => 'Owner id', // @translate
                    'o:is_public' => 'Visibility public/private', // @translate
                    // For item set.
                    'o:is_open' => 'Openness', // @translate
                    'properties' => 'All used properties', // @translate
                ],
            ],
            'o:item_set' => [
                'label' => 'Item set (for item)', // @translate
                'options' => [
                    'o:item_set[o:id]' => 'Internal id', // @translate
                    'o:item_set[dcterms:identifier]' => 'Identifier', // @translate
                    'o:item_set[dcterms:title]' => 'Label (first title)', // @translate
                ],
            ],
            'o:media' => [
                'label' => 'Media (for item)', // @translate
                'options' => [
                    'o:media[o:id]' => 'Internal id', // @translate
                    'o:media[file]' => 'Url / File', // @translate
                    'o:media[dcterms:identifier]' => 'Identifier', // @translate
                    'o:media[dcterms:title]' => 'Label (first title)', // @translate
                ],
            ],
            'o:item' => [
                'label' => 'Item (for media)', // @translate
                'options' => [
                    'o:item[o:id]' => 'Internal id', // @translate
                    'o:item[dcterms:identifier]' => 'Identifier', // @translate
                    'o:item[dcterms:title]' => 'Label (first title)', // @translate
                ],
            ],
        ];
    }
}
