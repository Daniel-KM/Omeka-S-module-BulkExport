<?php declare(strict_types=1);
namespace BulkExport\Controller\Admin;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;

class ExportController extends AbstractActionController
{
    use ServiceLocatorAwareTrait;

    /**
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
    }

    public function indexAction()
    {
        $this->setBrowseDefaults('started');

        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery();

        $response = $this->api()->search('bulk_exports', $query);
        $this->paginator($response->getTotalResults(), $page);

        $exports = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('exports', $exports);
        $view->setVariable('resources', $exports);
        return $view;
    }

    public function showAction()
    {
        $id = $this->params()->fromRoute('id');
        $export = $this->api()->read('bulk_exports', $id)->getContent();

        $view = new ViewModel;
        $view->setVariable('export', $export);
        $view->setVariable('resource', $export);
        return $view;
    }

    public function logsAction()
    {
        $id = $this->params()->fromRoute('id');
        $export = $this->api()->read('bulk_exports', $id)->getContent();

        $this->setBrowseDefaults('created');

        $severity = $this->params()->fromQuery('severity', Logger::NOTICE);
        $severity = (int) preg_replace('/[^0-9]+/', '', $severity);
        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery();
        $query['reference'] = 'bulk/export/' . $id;
        $query['severity'] = '<=' . $severity;

        $response = $this->api()->read('bulk_exports', $id);
        $this->paginator($response->getTotalResults(), $page);

        $response = $this->api()->search('logs', $query);
        $this->paginator($response->getTotalResults(), $page);

        $logs = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('export', $export);
        $view->setVariable('resource', $export);
        $view->setVariable('logs', $logs);
        $view->setVariable('severity', $severity);
        return $view;
    }
}
