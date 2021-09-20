<?php declare(strict_types=1);

namespace BulkExport\Controller\Admin;

use Laminas\Log\Logger;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ExportController extends AbstractActionController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('started');

        $page = $this->params()->fromQuery('page', 1);
        $query = $this->params()->fromQuery();

        $response = $this->api()->search('bulk_exports', $query);
        $this->paginator($response->getTotalResults(), $page);

        $exports = $response->getContent();

        return new ViewModel([
            'exports' => $exports,
            'resources' => $exports,
        ]);
    }

    public function showAction()
    {
        $id = $this->params()->fromRoute('id');
        $export = $this->api()->read('bulk_exports', $id)->getContent();

        return new ViewModel([
            'export' => $export,
            'resource' => $export,
        ]);
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

        return new ViewModel([
            'export' => $export,
            'resource' => $export,
            'logs' => $logs,
            'severity' => $severity,
        ]);
    }
}
