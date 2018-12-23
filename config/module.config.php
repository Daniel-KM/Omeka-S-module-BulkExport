<?php
namespace BulkImport;

return [
    'service_manager' => [
        'factories' => [
            Log\Logger::class => Service\Log\LoggerFactory::class,
            Processor\Manager::class => Service\Plugin\PluginManagerFactory::class,
            Reader\Manager::class => Service\Plugin\PluginManagerFactory::class,
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
            'import_importers' => Api\Adapter\ImporterAdapter::class,
            'import_imports' => Api\Adapter\ImportAdapter::class,
            'import_logs' => Api\Adapter\LogAdapter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\CsvReaderConfigForm::class => Service\Form\FormFactory::class,
            Form\CsvReaderParamsForm::class => Service\Form\FormFactory::class,
            Form\ImporterDeleteForm::class => Service\Form\FormFactory::class,
            Form\ImporterForm::class => Service\Form\FormFactory::class,
            Form\ImporterStartForm::class => Service\Form\FormFactory::class,
            Form\ItemsProcessorConfigForm::class => Service\Form\FormFactory::class,
            Form\ItemsProcessorParamsForm::class => Service\Form\FormFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            // Class is not used as key, since it's set dynamically by sub-route
            // and it should be available in acl (so alias is mapped later).
            'BulkImport\Controller\Admin\Import' => Service\Controller\ControllerFactory::class,
            'BulkImport\Controller\Admin\Importer' => Service\Controller\ControllerFactory::class,
            'BulkImport\Controller\Admin\Index' => Service\Controller\ControllerFactory::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Import', // @translate
                'route' => 'admin/import',
                'resource' => 'BulkImport\Controller\Admin\Index',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'import' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route'    => '/import',
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkImport\Controller\Admin',
                                '__ADMIN__' => true,
                                'controller' => 'Index',
                                'action'     => 'index',
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
];
