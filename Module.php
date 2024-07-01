<?php declare(strict_types=1);

namespace BulkExport;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;

/**
 * Bulk Export.
 *
 * @copyright Daniel Berthereau, 2018-2024
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'Log',
    ];

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /**
         * @var \Omeka\Permissions\Acl $acl
         * @see \Omeka\Service\AclFactory
         */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $roles = $acl->getRoles();
        $backendRoles = array_diff($roles, ['guest']);
        $baseRoles = array_diff($backendRoles, ['editor', 'site_admin', 'global_admin']);

        $acl
            // Anybody can read stream output from api, local api or views.
            ->allow(
                null,
                ['BulkExport\Controller\Output'],
                ['index', 'browse', 'show']
            )
            ->allow(
                null,
                [
                    'BulkExport\Controller\Omeka\Controller\Api',
                    'BulkExport\Controller\Omeka\Controller\ApiLocal',
                ]
            )

            // Admin part.
            // Any back-end roles can export via background job.
            // User lower than editor can only edit own exporters.
            // Editor and admins can edit all of them.
            // TODO Rights on exports and deletion.
            ->allow(
                $backendRoles,
                ['BulkExport\Controller\Admin\BulkExport']
            )
            ->allow(
                $backendRoles,
                ['BulkExport\Controller\Admin\Exporter'],
                ['add', 'start', 'edit', 'configure', 'delete']
            )
            ->allow(
                $backendRoles,
                ['BulkExport\Controller\Admin\Export'],
                ['browse', 'index', 'show', 'logs', 'delete-confirm', 'delete']
            )
            ->allow(
                $backendRoles,
                [
                    \BulkExport\Api\Adapter\ExporterAdapter::class,
                    \BulkExport\Api\Adapter\ExportAdapter::class,
                ],
                ['search', 'read', 'create', 'update', 'delete']
            )

            ->allow(
                $baseRoles,
                [
                    \BulkExport\Entity\Exporter::class,
                    \BulkExport\Entity\Export::class,
                ],
                ['create']
            )
            ->allow(
                $baseRoles,
                [
                    \BulkExport\Entity\Exporter::class,
                    \BulkExport\Entity\Export::class,
                ],
                ['read', 'update', 'delete'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion
            )

            ->allow(
                ['editor'],
                [
                    \BulkExport\Entity\Exporter::class,
                    \BulkExport\Entity\Export::class,
                ],
                ['create', 'read', 'update']
            )
            ->allow(
                ['editor'],
                [
                    \BulkExport\Entity\Exporter::class,
                    \BulkExport\Entity\Export::class,
                ],
                ['delete'],
                new \Omeka\Permissions\Assertion\OwnsEntityAssertion
            )
        ;
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.60')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.60'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $translator = $services->get('MvcTranslator');

        if (!$this->checkDestinationDir($basePath . '/bulk_export')) {
            $message = new PsrMessage(
                'The directory "{path}" is not writeable.', // @translate
                ['path' => $basePath . '/bulk_export']
            );
            throw new ModuleCannotInstallException((string) $message->setTranslator($translator));
        }
    }

    protected function postInstall(): void
    {
        $directory = new \RecursiveDirectoryIterator(__DIR__ . '/data/exporters', \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $filepath => $file) {
            $this->installExporter($filepath);
        }
    }

    protected function installExporter($filepath): void
    {
        // The resource "bulk_exporters" is not available during upgrade.
        require_once __DIR__ . '/src/Entity/Export.php';
        require_once __DIR__ . '/src/Entity/Exporter.php';

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');

        $user = $services->get('Omeka\AuthenticationService')->getIdentity();

        $data = include $filepath;
        $data['owner'] = $user;
        $entity = new \BulkExport\Entity\Exporter();
        foreach ($data as $key => $value) {
            $method = 'set' . ucfirst($key);
            $entity->$method($value);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    protected function preUninstall(): void
    {
        if (!empty($_POST['remove-bulk-exports'])) {
            $config = $this->getServiceLocator()->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            $this->rmDir($basePath . '/bulk_export');
        }
    }

    public function warnUninstall(Event $event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $services = $this->getServiceLocator();
        $t = $services->get('MvcTranslator');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING:'); // @translate
        $html .= '</strong>';
        $html .= '</p>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('All bulk exports will be removed (folder "{folder}").'), // @translate
            $basePath . '/bulk_export'
        );
        $html .= '</p>';

        $html .= '<label><input name="remove-bulk-exports" type="checkbox" form="confirmform">';
        $html .= $t->translate('Remove bulk export directory'); // @translate
        $html .= '</label>';

        echo $html;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // Append the links to output formats.
        $controllers = [
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'handleViewShowAfter']
            );
            $sharedEventManager->attach(
                $controller,
                'view.browse.after',
                [$this, 'handleViewBrowseAfter']
            );
        }
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.show.sidebar',
                [$this, 'handleViewShowAfterAdmin']
            );
            $sharedEventManager->attach(
                $controller,
                'view.details',
                [$this, 'handleViewShowAfterAdmin']
            );
            $sharedEventManager->attach(
                $controller,
                'view.browse.after',
                [$this, 'handleViewBrowseAfterAdmin']
            );
        }

        $sharedEventManager->attach(
            'Selection\Controller\Site\GuestBoard',
            'view.browse.after',
            [$this, 'handleViewBrowseAfterSelection']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_input_filters',
            [$this, 'handleMainSettingsFilters']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'handleSiteSettingsFilters']
        );

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
    }

    public function handleMainSettingsFilters(Event $event): void
    {
        $inputFilter = version_compare(\Omeka\Module::VERSION, '4', '<')
            ? $event->getParam('inputFilter')->get('bulkexport')
            : $event->getParam('inputFilter');
        $inputFilter
                ->add([
                    'name' => 'bulkexport_formatters',
                    'required' => false,
                ])
                ->add([
                    'name' => 'bulkexport_metadata',
                    'required' => false,
                ])
                ->add([
                    'name' => 'bulkexport_metadata_exclude',
                    'required' => false,
                ]);
        return;
    }

    public function handleSiteSettingsFilters(Event $event): void
    {
        $inputFilter = version_compare(\Omeka\Module::VERSION, '4', '<')
            ? $event->getParam('inputFilter')->get('bulkexport')
            : $event->getParam('inputFilter');
        $inputFilter
            ->add([
                'name' => 'bulkexport_formatters',
                'required' => false,
            ])
            ->add([
                'name' => 'bulkexport_metadata',
                'required' => false,
            ])
            ->add([
                'name' => 'bulkexport_metadata_exclude',
                'required' => false,
            ]);
    }

    public function handleViewShowAfter(Event $event): void
    {
        /**
         * @var \Laminas\View\Renderer\PhpRenderer $view
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         */
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');

        $allowed = [
            'item_show' => 'items',
            'itemset_show' => 'item_sets',
            'media_show' => 'media',
        ];
        $bulkExportViews = $siteSettings->get('bulkexport_views');
        $bulkExportViews = array_intersect_key($allowed, array_fill_keys($bulkExportViews, null));
        if (!count($bulkExportViews)) {
            return;
        }

        $view = $event->getTarget();
        $vars = $view->vars();

        $resource = $vars->offsetGet('resource');
        $resourceName = $resource->resourceName();
        if (!in_array($resourceName, $bulkExportViews)) {
            return;
        }

        echo $view->bulkExport($resource, [
            'site' => $vars->offsetGet('site'),
            'heading' => $view->translate('Export'), // @translate
        ]);
    }

    public function handleViewShowAfterAdmin(Event $event): void
    {
        $view = $event->getTarget();
        $vars = $view->vars();
        $resource = $vars->offsetGet('resource');
        echo $view->bulkExport($resource, [
            'heading' => $view->translate('Export'), // @translate
            'divclass' => 'meta-group',
        ]);
    }

    public function handleViewBrowseAfter(Event $event): void
    {
        /**
         * @var \Laminas\View\Renderer\PhpRenderer $view
         * @var \Common\Stdlib\EasyMeta $easyMeta
         */
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');

        $allowed = [
            'item_browse' => 'items',
            'itemset_browse' => 'item_sets',
            'media_browse' => 'media',
        ];
        $bulkExportViews = $siteSettings->get('bulkexport_views');
        $bulkExportViews = array_intersect_key($allowed, array_fill_keys($bulkExportViews, null));
        if (!count($bulkExportViews)) {
            return;
        }

        $easyMeta = $services->get('EasyMeta');

        $view = $event->getTarget();
        $params = $view->params();
        $paramsRoute = $params->fromRoute();
        $controller = $paramsRoute['__CONTROLLER'] ?? $paramsRoute['controller'] ?? null;
        $resourceName = $easyMeta->resourceName($controller);
        if (!in_array($resourceName, $bulkExportViews)) {
            return;
        }

        $query = $params->fromQuery() ?: [];

        // Set site early.
        $site = $view->currentSite();
        $query['site_id'] = $site->id();

        // Manage exception for item-set/show early.
        $itemSetId = $paramsRoute['item-set-id'] ?? null;
        if ($itemSetId) {
            $query['item_set_id'] = $itemSetId;
        }

        // Get all resources of the result, not only the first page.
        // There is a specific limit for the number of resources to output.
        // For longer output, use job process for now.
        unset($query['page'], $query['limit']);

        echo $view->bulkExport($query);
    }

    public function handleViewBrowseAfterAdmin(Event $event): void
    {
        /** @var \Laminas\View\Renderer\PhpRenderer $view */
        $view = $event->getTarget();
        $params = $view->params();
        $query = $params->fromQuery() ?: [];

        // Get all resources of the result, not only the first page.
        // There is a specific limit for the number of resources to output.
        // For longer output, use job process for now.
        unset($query['page'], $query['limit']);

        echo $view->bulkExport($query);
    }

    public function handleViewBrowseAfterSelection(Event $event): void
    {
        $view = $event->getTarget();
        $resourceType = 'resource';
        $user = $view->identity();
        if ($user) {
            $request = $view->params()->fromQuery();
            $request['owner_id'] = $user->getId();
            unset($request['page'], $request['limit']);
            // The view helper api doesn't manage options (returnScalar).
            $api = $this->getServiceLocator()->get('Omeka\ApiManager');
            $query = $api->search('selection_resources', $request, ['returnScalar' => 'resource'])->getContent();
        }

        if (empty($query)) {
            $query = null;
        }

        echo $view->bulkExport($query, [
            'site' => $view->vars()->offsetGet('site'),
            'exporters' => $view->bulkExporters(),
            'resourceType' => $resourceType,
        ]);
    }
}
