<?php declare(strict_types=1);

namespace BulkExport;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Log\Stdlib\PsrMessage;
use Omeka\Module\Exception\ModuleCannotInstallException;

class Module extends AbstractModule
{
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
            // Anybody can read stream output.
            ->allow(
                null,
                ['BulkExport\Controller\Output'],
                ['browse', 'show']
            )

            // Admin part.
            // Any back-end roles can export via background job.
            // User < editor can only edit own exporters.
            // Editor and admins can edit all of them.
            // TODO Rights on exports and deletion.
            ->allow(
                $backendRoles,
                ['BulkExport\Controller\Admin\BulkExport'],
                ['browse', 'index']
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

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!$this->checkDestinationDir($basePath . '/bulk_export')) {
            $message = new PsrMessage(
                'The directory "{path}" is not writeable.', // @translate
                ['path' => $basePath . '/bulk_export']
            );
            throw new ModuleCannotInstallException((string) $message);
        }

        // The version of Box/Spout should be >= 3.0, so check modules that use
        // it. Furthermore, check other modules for compatibility.
        $modules = [
            ['name' => 'Generic', 'version' => '3.3.34', 'required' => false],
            ['name' => 'BulkImport', 'version' => '3.3.21', 'required' => false],
            ['name' => 'CSVImport', 'version' => '2.3.0', 'required' => false],
            ['name' => 'CustomVocab', 'version' => '1.6.0', 'required' => false],
        ];
        foreach ($modules as $moduleData) {
            if (method_exists($this, 'checkModuleAvailability')) {
                $this->checkModuleAvailability($moduleData['name'], $moduleData['version'], $moduleData['required'], true);
            } else {
                // @todo Adaptation from Generic method, to be removed in next version.
                $moduleName = $moduleData['name'];
                $version = $moduleData['version'];
                $required = $moduleData['required'];
                $module = $services->get('Omeka\ModuleManager')->getModule($moduleName);
                if (!$module || !$this->isModuleActive($moduleName)) {
                    if (!$required) {
                        continue;
                    }
                    // Else throw message below (required module).
                } elseif (!$version || version_compare($module->getIni('version') ?? '', $version, '>=')) {
                    continue;
                }
                $translator = $services->get('MvcTranslator');
                $message = new \Omeka\Stdlib\Message(
                    $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                    $moduleName, $version
                );
                throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
            }
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

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');
        $config = $this->getServiceLocator()->get('Config');
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
                [$this, 'handleViewShowAfter']
            );
            $sharedEventManager->attach(
                $controller,
                'view.details',
                [$this, 'handleViewShowAfter']
            );
            $sharedEventManager->attach(
                $controller,
                'view.browse.after',
                [$this, 'handleViewBrowseAfter']
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
        $view = $event->getTarget();
        $vars = $view->vars();
        $resource = $vars->offsetGet('resource');
        echo $view->bulkExport($resource, [
            'site' => $vars->offsetGet('site'),
            'exporters' => $view->bulkExporters(),
            'resourceType' => $resource->getControllerName(),
        ]);
    }

    public function handleViewBrowseAfter(Event $event): void
    {
        $view = $event->getTarget();

        $resourceTypes = [
            'item' => 'item',
            'item-set' => 'item-set',
            'media' => 'media',
            'annotation' => 'annotation',
            'omeka\controller\site\item' => 'item',
            'omeka\controller\site\itemSet' => 'item-set',
            'omeka\controller\site\media' => 'media',
            'annotate\controller\site\annotation' => 'annotation',
            'omeka\controller\admin\item' => 'item',
            'omeka\controller\admin\itemset' => 'item-set',
            'omeka\controller\admin\media' => 'media',
            'annotate\controller\admin\annotation' => 'annotation',
        ];
        $params = $view->params();
        $controller = $params->fromRoute('__CONTROLLER__') ?? $params->fromRoute('controller', '');
        $resourceType = $resourceTypes[strtolower($controller)] ?? 'resource';

        $query = $view->params()->fromQuery();

        // Get all resources of the result, not only the first page.
        // There is a specific limit for the number of resources to output.
        // For longer output, use job process for now.
        unset($query['page'], $query['limit']);

        echo $view->bulkExport($query, [
            'site' => $view->vars()->offsetGet('site'),
            'exporters' => $view->bulkExporters(),
            'resourceType' => $resourceType,
        ]);
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

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path.
     * @return string|null
     */
    protected function checkDestinationDir(string $dirPath): ?string
    {
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_readable($dirPath) || !is_writeable($dirPath)) {
                $this->getServiceLocator()->get('Omeka\Logger')->err(
                    'The directory "{path}" is not writeable.', // @translate
                    ['path' => $dirPath]
                );
                return null;
            }
            return $dirPath;
        }

        $result = @mkdir($dirPath, 0775, true);
        if (!$result) {
            $this->getServiceLocator()->get('Omeka\Logger')->err(
                'The directory "{path}" is not writeable: {error}.', // @translate
                ['path' => $dirPath, 'error' => error_get_last()['message']]
            );
            return null;
        }
        return $dirPath;
    }

    /**
     * Remove a dir from filesystem.
     *
     * @param string $dirpath Absolute path.
     * @return bool
     */
    private function rmDir(string $dirPath): bool
    {
        if (!file_exists($dirPath)) {
            return true;
        }
        if (strpos($dirPath, '/..') !== false || substr($dirPath, 0, 1) !== '/') {
            return false;
        }
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $dirPath . '/' . $file;
            if (is_dir($path)) {
                $this->rmDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }
}
