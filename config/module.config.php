<?php declare(strict_types=1);

namespace BulkExport;

return [
    'service_manager' => [
        'factories' => [
            Formatter\Manager::class => Service\Plugin\FormatterManagerFactory::class,
            Writer\Manager::class => Service\Plugin\PluginManagerFactory::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'bulk_exporters' => Api\Adapter\ExporterAdapter::class,
            'bulk_exports' => Api\Adapter\ExportAdapter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'controller_map' => [
            Controller\Admin\BulkExportController::class => 'bulk/admin/bulk-export',
            Controller\Admin\ExportController::class => 'bulk/admin/export',
            Controller\Admin\ExporterController::class => 'bulk/admin/exporter',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'listFormatters' => Service\ViewHelper\ListFormattersFactory::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ExporterDeleteForm::class => Service\Form\FormFactory::class,
            Form\ExporterForm::class => Service\Form\FormFactory::class,
            Form\ExporterStartForm::class => Service\Form\FormFactory::class,
            Form\SettingsFieldset::class => Service\Form\SettingsFieldsetFactory::class,
            Form\SiteSettingsFieldset::class => Service\Form\SiteSettingsFieldsetFactory::class,
            Form\Writer\CsvWriterConfigForm::class => Service\Form\FormFactory::class,
            Form\Writer\SpreadsheetWriterConfigForm::class => Service\Form\FormFactory::class,
        ],
    ],
    'controllers' => [
        // Class is not used as key, since it's set dynamically by sub-route
        // and it should be available in acl (so alias is mapped later).
        'invokables' => [
            'BulkExport\Controller\Admin\BulkExport' => Controller\Admin\BulkExportController::class,
            'BulkExport\Controller\Admin\Export' => Controller\Admin\ExportController::class,
            'BulkExport\Controller\Output' => Controller\OutputController::class,
        ],
        'factories' => [
            'BulkExport\Controller\Admin\Exporter' => Service\Controller\ControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'exportFormatter' => Service\ControllerPlugin\ExportFormatterFactory::class,
        ],
    ],
    // TODO Merge bulk navigation and route with module BulkImport (require a main page?).
    'navigation' => [
        'AdminModule' => [
            'bulk-export' => [
                'label' => 'Bulk Export', // @translate
                'route' => 'admin/bulk-export/default',
                'controller' => 'bulk-export',
                'resource' => 'BulkExport\Controller\Admin\BulkExport',
                'class' => 'o-icon-uninstall',
                'pages' => [
                    [
                        'label' => 'Dashboard', // @translate
                        'route' => 'admin/bulk-export/default',
                        'controller' => 'bulk-export',
                        'resource' => 'BulkExport\Controller\Admin\BulkExport',
                        'pages' => [
                            [
                                'route' => 'admin/bulk-export/id',
                                'controller' => 'exporter',
                                'visible' => false,
                            ],
                        ],
                    ],
                    [
                        'label' => 'Past Exports', // @translate
                        'route' => 'admin/bulk-export/default',
                        'controller' => 'export',
                        'action' => 'index',
                        'pages' => [
                            [
                                'route' => 'admin/bulk-export/id',
                                'controller' => 'export',
                                'visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'resource-output' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:resource-type:.:format',
                            'constraints' => [
                                'resource-type' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'controller' => 'Output',
                                'action' => 'output',
                            ],
                        ],
                    ],
                    'resource-output-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:resource-type/:id:.:format',
                            'constraints' => [
                                'resource-type' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'controller' => 'Output',
                                'action' => 'output',
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'bulk-export' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/bulk-export',
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => 'BulkExport',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:controller[/:action]',
                                    'constraints' => [
                                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:controller/:id[/:action]',
                                    'constraints' => [
                                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'resource-output' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:resource-type:.:format',
                            'constraints' => [
                                'resource-type' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'controller' => 'Output',
                                'action' => 'output',
                            ],
                        ],
                    ],
                    'resource-output-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:resource-type/:id:.:format',
                            'constraints' => [
                                'resource-type' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'controller' => 'Output',
                                'action' => 'output',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    // Writers are designed for job processing and formatters for instant process.
    'bulk_export' => [
        // TODO Normalize writers as manageable or deprecate them.
        'writers' => [
            Writer\CsvWriter::class => Writer\CsvWriter::class,
            Writer\OpenDocumentSpreadsheetWriter::class => Writer\OpenDocumentSpreadsheetWriter::class,
            Writer\OpenDocumentTextWriter::class => Writer\OpenDocumentTextWriter::class,
            Writer\TextWriter::class => Writer\TextWriter::class,
            Writer\TsvWriter::class => Writer\TsvWriter::class,
        ],
    ],
    'formatters' => [
        'factories' => [
            Formatter\Csv::class => Service\Formatter\FormatterFactory::class,
            Formatter\Json::class => Service\Formatter\FormatterFactory::class,
            Formatter\JsonLd::class => Service\Formatter\FormatterFactory::class,
            Formatter\Ods::class => Service\Formatter\FormatterFactory::class,
            Formatter\Odt::class => Service\Formatter\FormatterFactory::class,
            Formatter\TemplateTxt::class => Service\Formatter\FormatterFactory::class,
            Formatter\Tsv::class => Service\Formatter\FormatterFactory::class,
            Formatter\Txt::class => Service\Formatter\FormatterFactory::class,
        ],
        'aliases' => [
            'csv' => Formatter\Csv::class,
            'json' => Formatter\Json::class,
            'json-ld' => Formatter\JsonLd::class,
            'list.txt' => Formatter\TemplateTxt::class,
            'ods' => Formatter\Ods::class,
            'odt' => Formatter\Odt::class,
            'tsv' => Formatter\Tsv::class,
            'txt' => Formatter\Txt::class,
        ],
    ],
    'bulkexport' => [
        'settings' => [
            'bulkexport_limit' => 1000,
            'bulkexport_formatters' => [
                'csv',
                // 'json',
                'json-ld',
                // 'list.txt',
                'ods',
                // 'odt',
                'tsv',
                'txt',
            ],
            'bulkexport_format_fields' => 'name',
            'bulkexport_format_generic' => 'string',
            'bulkexport_format_resource' => 'identifier_id',
            'bulkexport_format_resource_property' => 'dcterms:identifier',
            'bulkexport_format_uri' => 'uri_label',
            'bulkexport_metadata' => [
                'o:id',
                'o:resource_template',
                'o:resource_class',
                'o:owner',
                'o:is_public',
                'properties_small',
            ],
            'bulkexport_metadata_exclude' => [
                'properties_large',
                'extracttext:extracted_text',
            ],
            'bulkexport_template' => '',
        ],
        'site_settings' => [
            'bulkexport_limit' => 1000,
            'bulkexport_formatters' => [
                'csv',
                // 'json',
                'json-ld',
                // 'list.txt',
                'ods',
                // 'odt',
                'tsv',
                'txt',
            ],
            'bulkexport_format_fields' => 'label',
            'bulkexport_format_generic' => 'string',
            'bulkexport_format_resource' => 'url_title',
            'bulkexport_format_resource_property' => 'dcterms:identifier',
            'bulkexport_format_uri' => 'uri_label',
            'bulkexport_metadata' => [
                'url',
                'o:resource_class',
                'properties_small',
            ],
            'bulkexport_metadata_exclude' => [
                'properties_large',
                'extracttext:extracted_text',
            ],
            'bulkexport_template' => '',
        ],
    ],
];
