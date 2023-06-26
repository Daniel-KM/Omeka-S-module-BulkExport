<?php declare(strict_types=1);

namespace BulkExport;

return [
    'service_manager' => [
        'factories' => [
            Formatter\Manager::class => Service\FormatterManagerFactory::class,
            Writer\Manager::class => Service\PluginManagerFactory::class,
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
        'invokables' => [
            'bulkExport' => View\Helper\BulkExport::class,
        ],
        'factories' => [
            'bulkExporters' => Service\ViewHelper\BulkExportersFactory::class,
            // Copy from AdvancedResourceTemplate. Copy in BulkExport, BulkEdit and BulkImport. Used in Contribute.
            'customVocabBaseType' => Service\ViewHelper\CustomVocabBaseTypeFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ExporterDeleteForm::class => Form\ExporterDeleteForm::class,
            Form\ExporterStartForm::class => Form\ExporterStartForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
            Form\Writer\CsvWriterConfigForm::class => Form\Writer\CsvWriterConfigForm::class,
            Form\Writer\SpreadsheetWriterConfigForm::class => Form\Writer\SpreadsheetWriterConfigForm::class,
        ],
        'factories' => [
            Form\ExporterForm::class => Service\Form\ExporterFormFactory::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'bulkExport' => Site\ResourcePageBlockLayout\BulkExport::class,
        ],
    ],
    'controllers' => [
        // Class is not used as key, since it's set dynamically by sub-route
        // and it should be available in acl (so alias is mapped later).
        // TODO Find a way to keep class as main key as usual.
        'invokables' => [
            'BulkExport\Controller\Admin\BulkExport' => Controller\Admin\BulkExportController::class,
            'BulkExport\Controller\Admin\Export' => Controller\Admin\ExportController::class,
            'BulkExport\Controller\Admin\Exporter' => Controller\Admin\ExporterController::class,
            'BulkExport\Controller\Output' => Controller\OutputController::class,
        ],
        'factories' => [
            'BulkExport\Controller\Api' => Controller\ApiController::class,
        ],
        // The aliases simplify the routing, the url assembly and allows to support module Clean url.
        'aliases' => [
            // Api (examples: /api/items.ods, /api/item/151.odt).
            // ApiLocal (examples: /api-local/items.ods, /api-local/item/151.odt).
            // In the Omeka routes, the controller of the Api is already built,
            // so create a fake alias.
            'BulkExport\Controller\Omeka\Controller\Api' => Controller\ApiController::class,
            'BulkExport\Controller\Omeka\Controller\ApiLocal' => Controller\OutputController::class,
            // Views (examples: /admin/item.ods, /s/fr/151.ods).
            'BulkExport\Controller\Item' => Controller\OutputController::class,
            'BulkExport\Controller\ItemSet' => Controller\OutputController::class,
            'BulkExport\Controller\Media' => Controller\OutputController::class,
            'BulkExport\Controller\Resource' => Controller\OutputController::class,
            'BulkExport\Controller\Annotation' => Controller\OutputController::class,
            'BulkExport\Controller\CleanUrlController' => Controller\OutputController::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'exportFormatter' => Service\ControllerPlugin\ExportFormatterFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    // These routes allow to have a url compatible with Clean url.
                    'resource' => [
                        'may_terminate' => true,
                        'child_routes' => [
                            'output' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '.:format',
                                    'constraints' => [
                                        'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                        'action' => 'browse',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkExport\Controller',
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'resource-id' => [
                        'may_terminate' => true,
                        'child_routes' => [
                            'output' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '.:format',
                                    'constraints' => [
                                        'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                        'action' => 'show',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkExport\Controller',
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // These routes allow to create url without the action,
                    // but the routing matches main child routes.
                    'resource-output' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:controller:.:format',
                            'constraints' => [
                                'controller' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                'action' => 'browse',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'resource-output-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:controller/:id:.:format',
                            'constraints' => [
                                'controller' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                'id' => '\d+',
                                'action' => 'show',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'action' => 'show',
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
                        // TODO Check if these routes are still needed.
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
                    // These routes allow to have a url compatible with Clean url.
                    'default' => [
                        'may_terminate' => true,
                        'child_routes' => [
                            'output' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '.:format',
                                    'constraints' => [
                                        'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                        'action' => 'browse',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkExport\Controller',
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'id' => [
                        'may_terminate' => true,
                        'child_routes' => [
                            'output' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '.:format',
                                    'constraints' => [
                                        'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                        'action' => 'show',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkExport\Controller',
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // These routes allow to have a url without the action.
                    'resource-output' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:controller:.:format',
                            'constraints' => [
                                'controller' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                'action' => 'browse',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'resource-output-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/:controller/:id:.:format',
                            'constraints' => [
                                'controller' => 'resource|item-set|item|media|annotation',
                                'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                'id' => '\d+',
                                'action' => 'show',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'BulkExport\Controller',
                                'action' => 'show',
                            ],
                        ],
                    ],
                ],
            ],
            'api' => [
                // The controller of the api is defined statically.
                // 'controller' => 'Omeka\Controller\Api',
                'child_routes' => [
                    'default' => [
                        'may_terminate' => true,
                        'child_routes' => [
                            'output' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '.:format',
                                    'constraints' => [
                                        'controller' => 'resources|item_sets|items|media|annotations',
                                        'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkExport\Controller',
                                        'action' => 'index',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'api-local' => [
                // The controller of the api is defined statically.
                // The type and the options are added for compatibility with Omeka S < v4.1,
                // in particular to avoid an issue during upgrade.
                'type' => \Laminas\Router\Http\Literal::class,
                'options' => [
                    'route' => '/api-local',
                    /*
                    'defaults' => [
                        'controller' => 'Omeka\Controller\ApiLocal',
                    ],
                    */
                ],
                'child_routes' => [
                    'default' => [
                        'may_terminate' => true,
                        'child_routes' => [
                            'output' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '.:format',
                                    'constraints' => [
                                        'controller' => 'resources|item_sets|items|media|annotations',
                                        'format' => '[a-zA-Z0-9]+[a-zA-Z0-9.-]*',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'BulkExport\Controller',
                                        'action' => 'index',
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
    // TODO Merge bulk navigation and route with module BulkImport (require a main page?).
    'navigation' => [
        'AdminModule' => [
            'bulk-export' => [
                'label' => 'Bulk Export', // @translate
                'route' => 'admin/bulk-export/default',
                'controller' => 'bulk-export',
                'resource' => 'BulkExport\Controller\Admin\BulkExport',
                'class' => 'o-icon- fa-cloud-download-alt',
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
                        'action' => 'browse',
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
        'BulkExport' => [
            [
                'label' => 'Params', // @translate
                'route' => 'admin/bulk-export/id',
                'controller' => 'export',
                'action' => 'show',
                'useRouteMatch' => true,
            ],
            [
                'label' => 'Logs', // @translate
                'route' => 'admin/bulk-export/id',
                'controller' => 'export',
                'action' => 'logs',
                'useRouteMatch' => true,
            ],
        ],
    ],
    // Writers are designed for job processing and formatters for instant process.
    'bulk_export' => [
        // TODO Normalize writers as manageable or deprecate them
        // @deprecated To be removed. Only difference with formatters are manual settings or admin/site settings.
        'writers' => [
            Writer\CsvWriter::class => Writer\CsvWriter::class,
            Writer\GeoJsonWriter::class => Writer\GeoJsonWriter::class,
            Writer\JsonTableWriter::class => Writer\JsonTableWriter::class,
            Writer\OpenDocumentSpreadsheetWriter::class => Writer\OpenDocumentSpreadsheetWriter::class,
            Writer\OpenDocumentTextWriter::class => Writer\OpenDocumentTextWriter::class,
            Writer\TextWriter::class => Writer\TextWriter::class,
            Writer\TsvWriter::class => Writer\TsvWriter::class,
        ],
    ],
    // TODO Rss/Atom feeds.
    // TODO Rename the key "fomatters" as "exporters" or genericize.
    'formatters' => [
        'factories' => [
            Formatter\Csv::class => Service\PluginManagerFactory::class,
            Formatter\GeoJson::class => Service\PluginManagerFactory::class,
            Formatter\Json::class => Service\PluginManagerFactory::class,
            Formatter\JsonLd::class => Service\PluginManagerFactory::class,
            Formatter\JsonTable::class => Service\PluginManagerFactory::class,
            Formatter\Ods::class => Service\PluginManagerFactory::class,
            Formatter\Odt::class => Service\PluginManagerFactory::class,
            Formatter\TemplateTxt::class => Service\PluginManagerFactory::class,
            Formatter\Tsv::class => Service\PluginManagerFactory::class,
            Formatter\Txt::class => Service\PluginManagerFactory::class,
        ],
        'aliases' => [
            'csv' => Formatter\Csv::class,
            'geojson' => Formatter\GeoJson::class,
            'json' => Formatter\Json::class,
            'json-ld' => Formatter\JsonLd::class,
            'json-table' => Formatter\JsonTable::class,
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
                // 'geojson',
                // 'json',
                'json-ld',
                'json-table',
                // 'list.txt',
                'ods',
                // 'odt',
                'tsv',
                'txt',
            ],
            'bulkexport_format_fields' => 'name',
            'bulkexport_format_generic' => 'string',
            'bulkexport_format_resource' => 'id',
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
                // 'json-ld',
                'json-table',
                // 'list.txt',
                'ods',
                // 'odt',
                'tsv',
                'txt',
            ],
            'bulkexport_format_fields' => 'label',
            'bulkexport_format_generic' => 'string',
            'bulkexport_format_resource' => 'id',
            'bulkexport_format_resource_property' => 'dcterms:identifier',
            'bulkexport_format_uri' => 'uri_label',
            'bulkexport_metadata' => [
                'id',
                'url',
                'o:resource_class',
                'properties_small',
            ],
            'bulkexport_metadata_exclude' => [
                'o:owner',
                'properties_large',
                'extracttext:extracted_text',
            ],
            'bulkexport_template' => '',
        ],
    ],
];
