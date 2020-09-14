<?php
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
        'factories' => [
            // Class is not used as key, since it's set dynamically by sub-route
            // and it should be available in acl (so alias is mapped later).
            'BulkExport\Controller\Admin\BulkExport' => Service\Controller\ControllerFactory::class,
            'BulkExport\Controller\Admin\Export' => Service\Controller\ControllerFactory::class,
            'BulkExport\Controller\Admin\Exporter' => Service\Controller\ControllerFactory::class,
            'BulkExport\Controller\Output' => Service\Controller\OutputControllerFactory::class,
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
                        'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Literal::class,
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
                                'type' => \Zend\Router\Http\Segment::class,
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
                                'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Segment::class,
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
                        'type' => \Zend\Router\Http\Segment::class,
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
            Writer\TsvWriter::class => Writer\TsvWriter::class,
            Writer\OpenDocumentSpreadsheetWriter::class => Writer\OpenDocumentSpreadsheetWriter::class,
            Writer\TextWriter::class => Writer\TextWriter::class,
        ],
    ],
    'formatters' => [
        'factories' => [
            Formatter\Csv::class => Service\Formatter\FormatterFactory::class,
            Formatter\Json::class => Service\Formatter\FormatterFactory::class,
            Formatter\JsonLd::class => Service\Formatter\FormatterFactory::class,
            Formatter\Ods::class => Service\Formatter\FormatterFactory::class,
            Formatter\Tsv::class => Service\Formatter\FormatterFactory::class,
            Formatter\Txt::class => Service\Formatter\FormatterFactory::class,
        ],
        'aliases' => [
            'csv' => Formatter\Csv::class,
            'json' => Formatter\Json::class,
            'json-ld' => Formatter\JsonLd::class,
            'ods' => Formatter\Ods::class,
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
                'ods',
                'tsv',
                'txt',
            ],
            'bulkexport_format_fields' => 'name',
            'bulkexport_format_generic' => 'string',
            'bulkexport_format_resource' => 'url_title',
            'bulkexport_format_resource_property' => 'dcterms:identifier',
            'bulkexport_format_uri' => 'uri_label',
        ],
        'site_settings' => [
            'bulkexport_limit' => 1000,
            'bulkexport_formatters' => [
                'csv',
                // 'json',
                'json-ld',
                'ods',
                'tsv',
                'txt',
            ],
            'bulkexport_format_fields' => 'name',
            'bulkexport_format_generic' => 'string',
            'bulkexport_format_resource' => 'url_title',
            'bulkexport_format_resource_property' => 'dcterms:identifier',
            'bulkexport_format_uri' => 'uri_label',
        ],
    ],
];
