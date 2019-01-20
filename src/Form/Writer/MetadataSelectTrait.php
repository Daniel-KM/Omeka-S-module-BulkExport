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

    protected function prependMappingOptions()
    {
        return [
            'metadata' => [
                'label' => 'Resource metadata', // @translate
                'options' => [
                    'o:id' => 'Internal id', // @translate
                    'o:resource_template' => 'Resource template', // @translate
                    'o:resource_class' => 'Resource class', // @translate
                    'o:owner' => 'Owner', // @translate
                    'o:is_public' => 'Visibility public/private', // @translate
                    'properties' => 'All used properties', // @translate
                ],
            ],
        ];
    }
}
