<?php
namespace BulkExport\Form\Writer;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;

class SpreadsheetWriterConfigForm extends AbstractWriterConfigForm
{
    use MetadataSelectTrait;
    use ResourceTypesSelectTrait;
    use ResourceQueryTrait;
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $this
            ->add([
                'name' => 'separator',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Multi-value separator', // @translate
                    'info' => 'To output all values of each property, cells can be multivalued with this separator.
It is recommended to use a character that is never used, like "|", or a random string.', // @translate
                ],
                'attributes' => [
                    'id' => 'separator',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'format_generic',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Format of values', // @translate
                    'value_options' => [
                        'string' => 'String', // @translate
                        'html' => 'Html', // @translate
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
                        'id' => 'Id', // @translate
                        'identifier' => 'Identifier (property below)', // @translate
                        'identifier_id' => 'Identifier or id', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'format_resource',
                    'value' => 'id',
                ],
            ])
            ->add([
                'name' => 'format_resource_property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Resource property for identifier', // @translate
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
                    'label' => 'Uri', // @translate
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
        ;

        $this->appendResourceTypesSelect();
        $this->appendMetadataSelect();

        $this->appendResourceQuery();

        $this->addInputFilter();
        $this->addInputFilterResourceTypes();
        $this->addInputFilterMetadata();
        return $this;
    }

    protected function addInputFilter()
    {
        $this->getInputFilter()
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
