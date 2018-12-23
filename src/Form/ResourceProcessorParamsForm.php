<?php
namespace BulkImport\Form;

class ResourceProcessorParamsForm extends ResourceProcessorConfigForm
{
    public function init()
    {
        $this->baseFieldset();
        $this->addFieldsets();
        $this->addMapping();

        $this->baseInputFilter();
        $this->addInputFilter();
        $this->addMappingFilter();
    }

    protected function prependMappingOptions()
    {
        return [
            'metadata' => [
                'label' => 'Resource metadata', // @translate
                'options' => [
                    'resource_type' => 'Resource type', // @translate
                    'o:resource_template' => 'Resource template id', // @translate
                    'o:resource_class' => 'Resource class', // @translate
                    'o:is_public' => 'Visibility public/private', // @translate
                ],
            ],
            'item' => [
                'label' => 'Item', // @translate
                'options' => [
                    'o:item' => 'Internal id', // @translate
                ],
            ],
            'item_sets' => [
                'label' => 'Item sets', // @translate
                'options' => [
                    'o:item_set' => 'Internal id', // @translate
                    'o:is_open' => 'Openness', // @translate
                ],
            ],
            'media' => [
                'label' => 'Media', // @translate
                'options' => [
                    'url' => 'Url', // @translate
                    'sideload' => 'File (via sideload)', // @translate
                ],
            ],
        ];
    }
}
