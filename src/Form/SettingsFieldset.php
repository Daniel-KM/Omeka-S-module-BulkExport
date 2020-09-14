<?php
namespace BulkExport\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Bulk Export'; // @translate

    protected $formatters = [];

    public function init()
    {
        $this
            ->add([
                'name' => 'bulkexport_limit',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Maximum number of resources to export', // @translate
                    'info' => 'This setting is applied only for the direct output.', // @translate
                ],
                'attributes' => [
                    'id' => 'bulkexport_limit',
                    'min' => 0,
                ],
            ])
            ->add([
                'name' => 'bulkexport_formatters',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'label' => 'Formatters to display in resource pages', // @translate
                    'value_options' => $this->formatters,
                ],
                'attributes' => [
                    'id' => 'bulkexport_formatters',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_generic',
                'type' => Element\Radio::class,
                'options' => [
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
                    'label' => 'Format of linked resources', // @translate
                    'value_options' => [
                        'url_title' => 'Omeka url and title', // @translate
                        'url' => 'Omeka url', // @translate
                        'title' => 'Title', // @translate
                        'id' => 'Id', // @translate
                        'identifier' => 'Identifier (property below)', // @translate
                        'identifier_id' => 'Identifier or id', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_resource',
                    'value' => 'url_title',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_resource_property',
                'type' => PropertySelect::class,
                'options' => [
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
                    'label' => 'Format of uri', // @translate
                    'value_options' => [
                        'uri_label' => 'Uri and label separated by a space', // @translate
                        'uri' => 'Uri only', // @translate
                        'html' => 'Html', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_uri',
                    'value' => 'uri_label',
                ],
            ])
        ;
    }

    public function setFormatters(array $formatters)
    {
        $this->formatters = $formatters;
        return $this;
    }
}
