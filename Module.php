<?php
namespace BulkExport;

require_once __DIR__ . '/src/Module/AbstractGenericModule.php';

use BulkExport\Module\AbstractGenericModule;
use Omeka\Stdlib\Message;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractGenericModule
{
    protected $dependency = 'Log';

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (!$this->checkDestinationDir($basePath . '/bulk_export')) {
            $translator = $serviceLocator->get('MvcTranslator');
            $message = new Message(
                $translator->translate('The directory "%s" is not writeable.'), // @translate
                $basePath
            );
            throw new ModuleCannotInstallException($message);
        }

        parent::install($serviceLocator);
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        if (!empty($_POST['remove-bulk-exports'])) {
            $config = $this->getServiceLocator()->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            $this->rmDir($basePath . '/bulk_export');
        }

        parent::uninstall($serviceLocator);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
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
            $t->translate('All bulk exports will be removed (folder "%s".'), // @translate
            $basePath . '/bulk_export'
        );
        $html .= '</p>';

        $html .= '<label><input name="remove-bulk-exports" type="checkbox" form="confirmform">';
        $html .= $t->translate('Remove bulk export directory'); // @translate
        $html .= '</label>';

        echo $html;
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path.
     * @return string|null
     */
    protected function checkDestinationDir($dirPath)
    {
        if (!file_exists($dirPath)) {
            $services = $this->getServiceLocator();
            $config = $services->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            if (!is_writeable($basePath)) {
                $logger = $services->get('Omeka\Logger');
                $logger->err(new Message(
                    'The destination folder "%s" is not writeable.', // @translate
                    $basePath . '/' . $dirPath
                ));
                return;
            }
            @mkdir($dirPath, 0755, true);
        } elseif (!is_dir($dirPath) || !is_writeable($dirPath)) {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $logger->err(
                new Message('The destination folder "%s" is not writeable.', // @translate
                $dirPath
            ));
            return;
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
        $files = array_diff(scandir($dirPath), array('.', '..'));
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
