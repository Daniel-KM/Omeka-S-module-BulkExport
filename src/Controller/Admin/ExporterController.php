<?php declare(strict_types=1);

namespace BulkExport\Controller\Admin;

use BulkExport\Api\Representation\ExporterRepresentation;
use BulkExport\Form\ExporterDeleteForm;
use BulkExport\Form\ExporterForm;
use BulkExport\Form\ExporterStartForm;
use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Job\Export as JobExport;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;
use Common\Stdlib\PsrMessage;

class ExporterController extends AbstractActionController
{
    public function addAction()
    {
        return $this->editAction();
    }

    public function editAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        /** @var \BulkExport\Api\Representation\ExporterRepresentation $exporter */
        $exporter = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if ($id && !$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
        }

        $form = $this->getForm(ExporterForm::class);
        if ($exporter) {
            $currentData = $exporter->getJsonLd();
            $form->setData($currentData);
        }

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                unset($data['csrf'], $data['form_submit'], $data['current_form']);
                if (!isset($data['o:label']) || (string) $data['o:label'] === '') {
                    $data['o:label'] = $this->translate('[No label]'); // @translate
                }
                if (!$exporter) {
                    $data['o:owner'] = $this->identity();
                    $response = $this->api($form)->create('bulk_exporters', $data);
                } else {
                    $oConfig = array_replace(['exporter' => [], 'writer' => []], $currentData['o:config']);
                    $oConfig['exporter'] = $data['o:config']['exporter'] ?? [];
                    $data['o:config'] = $oConfig;
                    $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $data, [], ['isPartial' => true]);
                }

                if (!$response) {
                    $this->messenger()->addError('Save of exporter failed'); // @translate
                    return $id
                        ? $this->redirect()->toRoute('admin/bulk-export/id', [], true)
                        : $this->redirect()->toRoute('admin/bulk-export');
                } else {
                    $this->messenger()->addSuccess('Exporter successfully saved'); // @translate
                    return $this->redirect()->toRoute('admin/bulk-export');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'exporter' => $exporter,
            'form' => $form,
        ]);
    }

    public function deleteAction()
    {
        /** @var \BulkExport\Api\Representation\ExporterRepresentation $exporter */
        $id = (int) $this->params()->fromRoute('id');
        $exporter = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if (!$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export');
        }

        // Check if the exporter has exports.
        // Don't load entities if the only information needed is total results.
        $total = $this->api()->search('bulk_exports', ['exporter_id' => $id, 'limit' => 0])->getTotalResults();
        if ($total) {
            $this->messenger()->addWarning('This exporter cannot be deleted: exports that use it exist.'); // @translate
            return $this->redirect()->toRoute('admin/bulk-export');
        }

        $form = $this->getForm(ExporterDeleteForm::class);
        $form->setData($exporter->getJsonLd());

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->delete('bulk_exporters', $id);
                if ($response) {
                    $this->messenger()->addSuccess('Exporter successfully deleted'); // @translate
                } else {
                    $this->messenger()->addError('Delete of exporter failed'); // @translate
                }
                return $this->redirect()->toRoute('admin/bulk-export');
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'exporter' => $exporter,
            'form' => $form,
        ]);
    }

    public function configureWriterAction()
    {
        /** @var \BulkExport\Api\Representation\ExporterRepresentation $exporter */
        $id = (int) $this->params()->fromRoute('id');
        $exporter = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if (!$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export');
        }

        /** @var \BulkExport\Writer\WriterInterface $writer */
        $writer = $exporter->writer();
        $form = $this->getForm($writer->getConfigFormClass());
        $form->setAttribute('id', 'form-exporter-writer');
        $writerConfig = $writer instanceof Configurable ? $writer->getConfig() : [];
        $form->setData($writerConfig);

        $form->add([
            'name' => 'form_submit',
            'type' => Fieldset::class,
        ]);
        $form->get('form_submit')->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'options' => [
                'label' => 'Save',
            ],
            'attributes' => [
                'value' => 'Save', // @translate
                'id' => 'submitbutton',
            ],
        ]);

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $currentData = $exporter->getJsonLd();
                $currentData['o:config']['writer'] = $writer->handleConfigForm($form)->getConfig();
                $update = ['o:config' => $currentData['o:config']];
                $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $update, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess('Writer configuration saved'); // @translate
                } else {
                    $this->messenger()->addError('Save of writer configuration failed'); // @translate
                }
                return $this->redirect()->toRoute('admin/bulk-export');
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'exporter' => $exporter,
            'writer' => $writer,
            'form' => $form,
        ]);
    }

    /**
     * Process a bulk export by step: writer and confirm.
     *
     * @todo Simplify code of this three steps process.
     * @todo Move to ExportController.
     *
     * @return \Laminas\Http\Response|\Laminas\View\Model\ViewModel
     */
    public function startAction()
    {
        $id = (int) $this->params()->fromRoute('id');

        /** @var \BulkExport\Api\Representation\ExporterRepresentation $exporter */
        $exporter = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;
        if (!$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export');
        }

        /** @var \BulkExport\Writer\WriterInterface $writer */
        $writer = $exporter->writer();
        if (!$writer) {
            $message = new PsrMessage('Writer "{writer}" does not exist', ['writer' => $exporter->writerClass()]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export');
        }

        /** @var \Laminas\Session\SessionManager $sessionManager */
        $sessionManager = Container::getDefaultManager();
        $session = new Container('BulkExport', $sessionManager);

        if (!$this->getRequest()->isPost()) {
            $session->exchangeArray([]);
        }
        if (isset($session->writer)) {
            $writer->setParams($session->writer);
        }

        $formsCallbacks = $this->getStartFormsCallbacks($exporter);
        $formCallback = reset($formsCallbacks);

        $asTask = $exporter->configOption('exporter', 'as_task');
        if ($asTask) {
            $message = new PsrMessage('This export will be stored to be run as a task.'); // @translate
            $this->messenger()->addWarning($message);
        }

        $next = null;
        if ($this->getRequest()->isPost()) {
            // Current form.
            $currentForm = $this->getRequest()->getPost('current_form');

            // Avoid an issue if the user reloads the page.
            if (!isset($formsCallbacks[$currentForm])) {
                $message = new PsrMessage('The page was reloaded, but params are lost. Restart the export.'); // @translate
                $this->messenger()->addError($message);
                return $this->redirect()->toRoute('admin/bulk-export');
            }

            $form = call_user_func($formsCallbacks[$currentForm]);

            // Make certain to merge the files info if any!
            $request = $this->getRequest();
            $postData = $request->getPost()->toArray();
            $postFiles = $request->getFiles()->toArray();
            $data = array_merge_recursive($postData, $postFiles);

            // Pass data to form.
            $form->setData($data);
            if ($form->isValid()) {
                // Execute file filters.
                $data = $form->getData();
                unset($data['csrf'], $data['form_submit'], $data['current_form']);
                $session->{$currentForm} = $data;
                switch ($currentForm) {
                    default:
                    case 'writer':
                        $writer->handleParamsForm($form);
                        $session->comment = trim((string) $data['comment']);
                        $session->useBackground = !empty($data['use_background']);
                        $session->writer = $writer->getParams();
                        if (!$writer->isValid()) {
                            $this->messenger()->addError($writer->getLastErrorMessage());
                            $next = 'writer';
                        } else {
                            $next = 'confirm';
                        }
                        $formCallback = $formsCallbacks[$next];
                        break;

                    case 'confirm':
                        $exportData = [];
                        $exportData['o:owner'] = $this->identity();
                        $exportData['o-bulk:comment'] = trim((string) $session['comment']) ?: null;
                        $exportData['o-bulk:exporter'] = $exporter->getResource();
                        if ($writer instanceof Parametrizable) {
                            $writerParams = $writer->getParams();
                        } else {
                            $writerParams = [];
                        }

                        // Add some default params.
                        // TODO Make all writers parametrizable.
                        // @see \BulkExport\Controller\OutputController::output().
                        $writerParams['site_slug'] = null;
                        $writerParams['is_site_request'] = false;

                        $exportData['o:params'] = [
                            'writer' => $writerParams,
                        ];
                        $response = $this->api()->create('bulk_exports', $exportData);
                        if (!$response) {
                            $this->messenger()->addError('Save of export failed'); // @translate
                            break;
                        }

                        /** @var \BulkExport\Api\Representation\ExportRepresentation $export */
                        $export = $response->getContent();

                        // Don't run job if it is configured as a task.
                        if ($asTask) {
                            $message = new PsrMessage(
                                'The export #{bulk_export} was stored for future use.', // @translate
                                ['bulk_export' => $export->id()]
                            );
                            $this->messenger()->addSuccess($message);
                            return $this->redirect()->toRoute('admin/bulk-export');
                        }

                        $useBackground = $session->useBackground;

                        // Clear export session.
                        $session->exchangeArray([]);

                        $args = [
                            'bulk_export_id' => $export->id(),
                        ];

                        /** @var \Omeka\Job\Dispatcher $dispatcher */
                        $dispatcher = $this->jobDispatcher();
                        try {
                            $job = $useBackground
                                ? $dispatcher->dispatch(JobExport::class, $args)
                                : $dispatcher->dispatch(JobExport::class, $args, $export->getServiceLocator()->get(\Omeka\Job\DispatchStrategy\Synchronous::class));
                            $urlPlugin = $this->url();
                            $message = $useBackground
                                ? 'Export started in background (job {link_open_job}#{jobId}{link_close}, {link_open_log}logs{link_close}). This may take a while.' // @translate
                                : 'Export processed in (job {link_open_job}#{jobId}{link_close}, {link_open_log}logs{link_close}).'; // @translate
                            $message = new PsrMessage(
                                $message,
                                [
                                    'link_open_job' => sprintf(
                                        '<a href="%s">',
                                        htmlspecialchars($urlPlugin->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                                    ),
                                    'jobId' => $job->getId(),
                                    'link_close' => '</a>',
                                    'link_open_log' => sprintf(
                                        '<a href="%s">',
                                        htmlspecialchars($urlPlugin->fromRoute('admin/bulk-export/id', ['controller' => 'export', 'action' => 'logs', 'id' => $export->id()]))
                                    ),
                                ]
                            );
                            $message->setEscapeHtml(false);
                            $this->messenger()->addSuccess($message);
                        } catch (\Exception $e) {
                            $this->messenger()->addError('Export start failed'); // @translate
                        }

                        return $this->redirect()->toRoute('admin/bulk-export');
                }

                // Next form.
                $form = call_user_func($formCallback);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        // Default form.
        if (!isset($form)) {
            $form = call_user_func($formCallback);
        }

        if ($form instanceof \Laminas\Http\PhpEnvironment\Response) {
            return $form;
        }

        $view = new ViewModel([
            'exporter' => $exporter,
            'form' => $form,
            'step' => $next ?? 'writer',
            'steps' => array_keys(array_filter($formsCallbacks)),
        ]);

        if ($next === 'confirm') {
            $exportArgs = [];
            $exportArgs['comment'] = $session['comment'];
            $exportArgs['writer'] = $session['writer'];
            // For security purpose.
            unset($exportArgs['writer']['filename']);
            unset($exportArgs['writer']['export_id']);
            unset($exportArgs['writer']['exporter_label']);
            unset($exportArgs['writer']['export_started']);
            $view
                ->setVariable('exportArgs', $exportArgs);
        }

        return $view;
    }

    /**
     * @todo Replace by a standard multi-steps form without callback.
     */
    protected function getStartFormsCallbacks(ExporterRepresentation $exporter)
    {
        $controller = $this;
        $formsCallbacks = [];

        $writer = $exporter->writer();
        if ($writer instanceof Parametrizable) {
            /* @return \Laminas\Form\Form */
            $formsCallbacks['writer'] = function () use ($writer, $exporter, $controller) {
                $writerForm = $controller->getForm($writer->getParamsFormClass());
                $writerConfig = $writer instanceof Configurable ? $writer->getConfig() : [];
                $writerForm->setData($writerConfig);

                $writerForm->add([
                    'name' => 'current_form',
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'value' => 'writer',
                    ],
                ]);
                $writerForm->add([
                    'name' => 'form_submit',
                    'type' => Fieldset::class,
                ]);
                $writerForm->get('form_submit')->add([
                    'name' => 'submit',
                    'type' => Element\Submit::class,
                    'attributes' => [
                        'value' => 'Continue', // @translate
                    ],
                ]);

                return $writerForm;
            };
        }

        /* @return \Laminas\Form\Form */
        $formsCallbacks['confirm'] = function () use ($exporter, $controller) {
            $startForm = $controller->getForm(ExporterStartForm::class);
            $startForm->add([
                'name' => 'current_form',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'value' => 'confirm',
                ],
            ]);
            // Submit is in the fieldset.
            return $startForm;
        };

        return $formsCallbacks;
    }
}
