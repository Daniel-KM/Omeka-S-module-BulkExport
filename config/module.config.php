<?php

return [
    'service_manager' => [
        'factories' => [
            \Import\Reader\Manager::class => \Import\Service\Plugin\PluginManagerFactory::class,
            \Import\Processor\Manager::class => \Import\Service\Plugin\PluginManagerFactory::class,
            \Import\Log\Logger::class => \Import\Service\Log\LoggerFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'Import\Controller\Index' => \Import\Service\Controller\ControllerFactory::class,
            'Import\Controller\Imports' => \Import\Service\Controller\ControllerFactory::class,
            'Import\Controller\Importers' => \Import\Service\Controller\ControllerFactory::class,
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'import_importers' => \Import\Api\Adapter\ImporterAdapter::class,
            'import_imports' => \Import\Api\Adapter\ImportAdapter::class,
            'import_logs' => \Import\Api\Adapter\LogAdapter::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => OMEKA_PATH . '/modules/Import/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            OMEKA_PATH . '/modules/Import/view',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/Import/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'form_elements' => [
        'factories' => [
            \Import\Form\ImporterForm::class => \Import\Service\Form\FormFactory::class,
            \Import\Form\ImporterDeleteForm::class => \Import\Service\Form\FormFactory::class,
            \Import\Form\CsvReaderConfigForm::class => \Import\Service\Form\FormFactory::class,
            \Import\Form\CsvReaderParamsForm::class => \Import\Service\Form\FormFactory::class,
            \Import\Form\ItemsProcessorConfigForm::class => \Import\Service\Form\FormFactory::class,
            \Import\Form\ItemsProcessorParamsForm::class => \Import\Service\Form\FormFactory::class,
            \Import\Form\ImporterStartForm::class => \Import\Service\Form\FormFactory::class,
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
                                '__NAMESPACE__' => 'Import\Controller',
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
                                        '__NAMESPACE__' => 'Import\Controller',
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
                                        '__NAMESPACE__' => 'Import\Controller',
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
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Import',
                'route' => 'admin/import',
                'resource' => 'Import\Controller\Index',
            ],
        ],
    ],
];
