<?php
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

    protected $dependency = 'Log';

    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $this->getServiceLocator()->get('Omeka\Acl')
            ->allow(
                null,
                ['BulkExport\Controller\Output'],
                ['output']
            )
        ;
    }

    protected function preInstall()
    {
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!$this->checkDestinationDir($basePath . '/bulk_export')) {
            $message = new PsrMessage(
                'The directory "{path}" is not writeable.', // @translate
                ['path' => $basePath]
            );
            throw new ModuleCannotInstallException($message);
        }
    }

    protected function postInstall()
    {
        $directory = new \RecursiveDirectoryIterator(__DIR__ . '/data/exporters', \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory);
        foreach ($iterator as $filepath => $file) {
            $this->installExporter($filepath);
        }
    }

    protected function installExporter($filepath)
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

    protected function preUninstall()
    {
        if (!empty($_POST['remove-bulk-exports'])) {
            $config = $this->getServiceLocator()->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            $this->rmDir($basePath . '/bulk_export');
        }
    }

    public function warnUninstall(Event $event)
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

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
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
            [$this, 'handleViewBrowseAfter']
        );
        $sharedEventManager->attach(
            'Basket\Controller\Site\GuestBoard',
            'view.browse.after',
            [$this, 'handleViewBrowseAfter']
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

    public function handleMainSettingsFilters(Event $event)
    {
        $event->getParam('inputFilter')->get('bulkexport')
            ->add([
                'name' => 'bulkexport_formatters',
                'required' => false,
            ])
            ->add([
                'name' => 'bulkexport_metadata',
                'required' => false,
            ]);
    }

    public function handleSiteSettingsFilters(Event $event)
    {
        $event->getParam('inputFilter')->get('bulkexport')
            ->add([
                'name' => 'bulkexport_formatters',
                'required' => false,
            ])
            ->add([
                'name' => 'bulkexport_metadata',
                'required' => false,
            ]);
    }

    public function handleViewShowAfter(Event $event)
    {
        $view = $event->getTarget();
        $view->vars()->offsetSet('formatters', $view->listFormatters(true));
        echo $view->partial('common/bulk-export-formatters-resource');
    }

    public function handleViewBrowseAfter(Event $event)
    {
        $controller = strtolower($event->getTarget()->params()->fromRoute('__CONTROLLER__'));
        $resourceTypes = [
            'item',
            'item-set',
            'media',
            'annotation',
        ];
        $resourceType = in_array($controller, $resourceTypes) ? $controller : 'resource';
        $this->handleViewBrowseAfterResources($event, $resourceType);
    }

    public function handleViewBrowseAfterResources(Event $event, $resourceType = 'resource')
    {
        $view = $event->getTarget();
        $view->vars()->offsetSet('formatters', $view->listFormatters(true));
        $view->vars()->offsetSet('resourceType', $resourceType);
        echo $view->partial('common/bulk-export-formatters', $view->vars());
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path.
     * @return string|null
     */
    protected function checkDestinationDir($dirPath)
    {
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_readable($dirPath) || !is_writable($dirPath)) {
                $this->logger->err(
                    'The directory "{path}" is not writeable.', // @translate
                    ['path' => $dirPath]
                );
                return null;
            }
            return $dirPath;
        }

        $result = @mkdir($dirPath, 0775, true);
        if (!$result) {
            $this->logger->err(
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
    private function rmDir($dirPath)
    {
        if (!file_exists($dirPath)) {
            return true;
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
