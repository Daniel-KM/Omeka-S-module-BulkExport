<?php declare(strict_types=1);

namespace BulkExport\Controller\Admin;

use BulkExport\Form\ShaperForm;
use BulkExport\Form\ShaperDeleteForm;
use Common\Stdlib\PsrMessage;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;

/**
 * Adapted from omeka controllers
 *
 * @todo The browse view may be like the Menu view, so all edits can be done in one page.
 */
class ShaperController extends AbstractActionController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('label', 'asc');
        $this->browse()->setDefaults('bulk_shapers');

        /** @var \BulkExport\Api\Representation\ShaperRepresentation[] $shapers */
        $response = $this->api()->search('bulk_shapers', ['sort_by' => 'label', 'sort_order' => 'asc']);
        $shapers = $response->getContent();
        $this->paginator($response->getTotalResults());

        // Set the return query for batch actions. Note that we remove the page
        // from the query because there's no assurance that the page will return
        // results once changes are made.
        $returnQuery = $this->params()->fromQuery();
        unset($returnQuery['page']);

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], ['query' => $returnQuery], true));
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], ['query' => $returnQuery], true));
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        return new ViewModel([
            'shapers' => $shapers,
            'resources' => $shapers,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
            'returnQuery' => $returnQuery,
        ]);
    }

    public function showAction()
    {
        /** @var \BulkExport\Api\Representation\ShaperRepresentation $shaper */
        $response = $this->api()->read('bulk_shapers', $this->params('id'));
        $shaper = $response->getContent();

        return new ViewModel([
            'shaper' => $shaper,
            'resource' => $shaper,
        ]);
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        /** @var \BulkExport\Api\Representation\ShaperRepresentation $shaper */
        $response = $this->api()->read('bulk_shapers', $this->params('id'));
        $shaper = $response->getContent();

        return (new ViewModel([
            'shaper' => $shaper,
            'resource' => $shaper,
            'linkTitle' => $linkTitle,
        ]))->setTerminal(true);
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);

        /** @var \BulkExport\Api\Representation\ShaperRepresentation $shaper */
        $response = $this->api()->read('bulk_shapers', $this->params('id'));
        $shaper = $response->getContent();

        return (new ViewModel([
            'shaper' => $shaper,
            'resource' => $shaper,
            'linkTitle' => $linkTitle,
            'resourceLabel' => 'Shaper', // @translate
            'partialPath' => 'bulk/admin/shaper/show-details',
        ]))
            ->setTemplate('common/delete-confirm-details')
            ->setTerminal(true);
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ShaperDeleteForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api()->delete('bulk_shapers', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Shaper successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/bulk-export/default', ['action' => 'browse'], true);
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $returnQuery = $this->params()->fromQuery();
        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one shaper to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], ['query' => $returnQuery], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('bulk_shaper', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Shapers successfully deleted'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], ['query' => $returnQuery], true);
    }

    public function batchDeleteAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $this->jobDispatcher()->dispatch('Omeka\Job\BatchDelete', [
                'resource' => 'bulk_shapers',
                'query' => $query,
            ]);
            $this->messenger()->addSuccess('Deleting shapers. This may take a while.'); // @translate
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], ['query' => $this->params()->fromQuery()], true);
    }

    public function addAction()
    {
        return $this->addEdit('add');
    }

    public function editAction()
    {
        return $this->addEdit('edit');
    }

    protected function addEdit(string $action)
    {
        $id = (int) $this->params()->fromRoute('id');
        /** @var \BulkExport\Api\Representation\ShaperRepresentation $shaper */
        try {
            $shaper = $id ? $this->api()->read('bulk_shapers', ['id' => $id])->getContent() : null;
        } catch (\Exception $e) {
            // New shaper.
            $shaper = null;
        }

        if ($id && !$shaper) {
            $message = new PsrMessage('Shaper #{shaper_id} does not exist', ['shaper_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export/default', ['action' => 'browse'], true);
        }

        $form = $this->getForm(ShaperForm::class);
        if ($shaper) {
            $currentData = $shaper->getJsonLd();
            $form->setData($currentData);
        }

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                unset($data['csrf'], $data['form_submit'], $data['current_form']);
                if (!isset($data['o:label']) || (string) $data['o:label'] === '') {
                    // TODO Get the date time zone of the user.
                    $data['o:label'] = '[' . (new \DateTime('now'))->format('Y-m-d H:i:s') . ']';
                }
                if (!$shaper) {
                    $data['o:owner'] = $this->identity();
                    $response = $this->api($form)->create('bulk_shapers', $data);
                } else {
                    $response = $this->api($form)->update('bulk_shapers', $this->params('id'), $data, [], ['isPartial' => true]);
                }

                if (!$response) {
                    $this->messenger()->addError('Save of shaper failed'); // @translate
                    return $id
                        ? $this->redirect()->toRoute('admin/bulk-export/id', [], true)
                        : $this->redirect()->toRoute('admin/bulk-export/default', ['action' => 'browse'], true);
                } else {
                    $this->messenger()->addSuccess('Shaper successfully saved'); // @translate
                    return $this->redirect()->toRoute('admin/bulk-export/default', ['action' => 'browse'], true);
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'shaper' => $shaper,
            'form' => $form,
        ]);
    }
}
