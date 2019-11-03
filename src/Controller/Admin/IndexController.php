<?php
namespace BulkExport\Controller\Admin;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    use ServiceLocatorAwareTrait;

    /**
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
    }

    public function indexAction()
    {
        // Exporters.
        $response = $this->api()->search('bulk_exporters', ['sort_by' => 'label', 'sort_order' => 'asc']);
        $exporters = $response->getContent();

        $this->setBrowseDefaults('label', 'asc');

        // Exports.
        $perPage = 25;
        $query = [
            'page' => 1,
            'per_page' => $perPage,
            'sort_by' => 'id',
            'sort_order' => 'desc',
        ];
        $response = $this->api()->search('bulk_exports', $query);
        $this->paginator($response->getTotalResults(), 1);

        $exports = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('exporters', $exporters);
        $view->setVariable('exports', $exports);
        return $view;
    }
}
