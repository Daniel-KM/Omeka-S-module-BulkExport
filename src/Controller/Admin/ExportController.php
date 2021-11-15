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

        /*
        $formDeleteSelected = $this->getForm(\Omeka\Form\ConfirmForm::class);
        $formDeleteSelected
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true))
            ->setButtonLabel('Confirm Delete') // @translate
            ->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(\Omeka\Form\ConfirmForm::class);
        $formDeleteAll
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true))
            ->setButtonLabel('Confirm Delete') // @translate
            ->setAttribute('id', 'confirm-delete-all')
            ->get('submit')->setAttribute('disabled', true);
        */

        $exports = $response->getContent();

        return new ViewModel([
            'resources' => $exports,
            'exports' => $exports,
            // 'formDeleteSelected' => $formDeleteSelected,
            // 'formDeleteAll' => $formDeleteAll,
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

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $export = $this->api()->read('bulk_exports', $this->params('id'))->getContent();
        $view = new ViewModel([
            'resource' => $export,
            'resourceLabel' => 'export', // @translate
            'partialPath' => 'bulk/admin/export/show-details',
            'linkTitle' => $linkTitle,
            'export' => $export,
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('common/delete-confirm-details');
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(\Omeka\Form\ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('bulk_exports', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Export successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/bulk-export',
            ['action' => 'browse'],
            true
        );
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
