<?php
namespace BulkExport;

return [
    'service_manager' => [
        'factories' => [
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
            Controller\Admin\IndexController::class => 'bulk/admin/index',
            Controller\Admin\ExportController::class => 'bulk/admin/export',
            Controller\Admin\ExporterController::class => 'bulk/admin/exporter',
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ExporterDeleteForm::class => Service\Form\FormFactory::class,
            Form\ExporterForm::class => Service\Form\FormFactory::class,
            Form\ExporterStartForm::class => Service\Form\FormFactory::class,
            Form\Writer\CsvWriterConfigForm::class => Service\Form\FormFactory::class,
            Form\Writer\CsvWriterParamsForm::class => Service\Form\FormFactory::class,
            Form\Writer\OpenDocumentSpreadsheetWriterParamsForm::class => Service\Form\FormFactory::class,
            Form\Writer\SpreadsheetWriterConfigForm::class => Service\Form\FormFactory::class,
            Form\Writer\SpreadsheetWriterParamsForm::class => Service\Form\FormFactory::class,
            Form\Writer\TsvWriterParamsForm::class => Service\Form\FormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            // Class is not used as key, since it's set dynamically by sub-route
            // and it should be available in acl (so alias is mapped later).
            'BulkExport\Controller\Admin\Export' => Service\Controller\ControllerFactory::class,
            'BulkExport\Controller\Admin\Exporter' => Service\Controller\ControllerFactory::class,
            'BulkExport\Controller\Admin\Index' => Service\Controller\ControllerFactory::class,
        ],
    ],
    // TODO Merge bulk navigation and route with module BulkImport (require a main page?).
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Bulk Export', // @translate
                'route' => 'admin/bulk-export',
                'resource' => 'BulkExport\Controller\Admin\Index',
                'class' => 'o-icon-uninstall',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'bulk-export' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/bulk-export',
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => 'Index',
                                'action' => 'export-board',
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
    'bulk_export' => [
        'writers' => [
            Writer\SpreadsheetWriter::class => Writer\SpreadsheetWriter::class,
            Writer\CsvWriter::class => Writer\CsvWriter::class,
            Writer\TsvWriter::class => Writer\TsvWriter::class,
            Writer\OpenDocumentSpreadsheetWriter::class => Writer\OpenDocumentSpreadsheetWriter::class,
        ],
    ],
];
