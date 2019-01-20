<?php
namespace BulkExport\Form\Writer;

use Zend\Form\Element;

trait ResourceTypesSelectTrait
{
    public function appendResourceTypesSelect()
    {
        $this->add([
            'name' => 'resource_types',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Resource types', // @translate
                'info' => 'When multiple types are selected, a column is automatically added to identify it.', // @translate
                'value_options' => $this->listResourceTypes(),
            ],
            'attributes' => [
                'id' => 'resource_types',
                'value' => ['items'],
                'required' => false,
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select one or more resource type…', // @translate
            ],
        ]);
    }

    protected function addInputFilterResourceTypes()
    {
        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'resource_types',
            'required' => false,
        ]);
    }

    /**
     * @todo Get the list of api adapters automatically from the config (or via api_resources).
     *
     * @return array
     */
    protected function listResourceTypes()
    {
        $resourceTypes = [
            // Core.
            // 'o:User' => 'Users', // @translate
            // 'o:Vocabulary' => 'Vocabularies', // @translate
            // 'o:ResourceClass' => 'Resource classes', // @translate
            // 'o:ResourceTemplate' => 'Resource templates', // @translate
            // 'o:Property' => 'Properties', // @translate
            'o:Item' => 'Items', // @translate
            'o:Media' => 'Media', // @translate
            'o:ItemSet' => 'Item sets', // @translate
            // 'o:Module' => 'Modules', // @translate
            // 'o:Site' => 'Sites', // @translate
            // 'o:SitePage' => 'Site pages', // @translate
            // 'o:Job' => 'Jobs', // @translate
            // 'o:Resource' => 'Resources', // @translate
            // 'o:Asset' => 'Assets', // @translate
            // 'o:ApiResource' => 'Api resources', // @translate
            // Modules.
            'oa:Annotation' => 'Annotations', // @translate
        ];

        if (!class_exists(\Annotate\Entity\Annotation::class)) {
            unset($resourceTypes['oa:Annotation']);
        }

        return $resourceTypes;
    }
}
