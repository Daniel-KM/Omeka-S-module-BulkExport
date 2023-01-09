<?php declare(strict_types=1);

namespace BulkExport\Form;

use BulkExport\Form\Writer\MetadataSelectTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element\PropertySelect;

class SiteSettingsFieldset extends Fieldset
{
    use MetadataSelectTrait;

    /**
     * @var string
     */
    protected $label = 'Bulk Export'; // @translate

    protected $elementGroups = [
        'export' => 'Export', // @translate
    ];

    protected $formatters = [];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'bulk-export')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'bulkexport_limit',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Maximum number of resources to export', // @translate
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
                    'element_group' => 'export',
                    'label' => 'Formatters to display in resource pages', // @translate
                    'value_options' => $this->formatters,
                ],
                'attributes' => [
                    'id' => 'bulkexport_formatters',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_fields',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Metadata names or headers', // @translate
                    'value_options' => [
                        'name' => 'Rdf names', // @translate
                        'label' => 'Labels', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_fields',
                    'value' => 'name',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_generic',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'export',
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
                    'element_group' => 'export',
                    'label' => 'Format of linked resources', // @translate
                    'value_options' => [
                        'url_title' => 'Omeka url and title', // @translate
                        'title' => 'Title', // @translate
                        'url' => 'Omeka url', // @translate
                        'identifier' => 'Identifier (property below)', // @translate
                        'id' => 'Id', // @translate
                        'identifier_id' => 'Identifier or id', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_format_resource',
                    'value' => 'id',
                ],
            ])
            ->add([
                'name' => 'bulkexport_format_resource_property',
                'type' => PropertySelect::class,
                'options' => [
                    'element_group' => 'export',
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
                    'element_group' => 'export',
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
        $this->appendMetadataSelect('bulkexport_metadata');
    }

    public function setFormatters(array $formatters)
    {
        $this->formatters = $formatters;
        return $this;
    }
}
