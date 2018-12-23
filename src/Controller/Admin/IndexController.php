<?php
namespace BulkImport\Controller\Admin;

use BulkImport\Traits\ServiceLocatorAwareTrait;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    use ServiceLocatorAwareTrait;

    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
    }

    public function indexAction()
    {
        // Importers.
        $importers = $this->api()->search('bulk_importers');

        // Imports.
        $perPage = 25;
        $query = [
            'page' => 1,
            'per_page' => $perPage,
            'sort_by' => $this->params()->fromQuery('sort_by', 'id'),
            'sort_order' => $this->params()->fromQuery('sort_order', 'desc'),
        ];
        $imports = $this->api()->search('bulk_imports', $query);

        $view = new ViewModel;
        $view->setVariable('importers', $importers->getContent());
        $view->setVariable('imports', $imports->getContent());
        return $view;
    }
}
