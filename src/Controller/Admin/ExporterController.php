<?php declare(strict_types=1);

namespace BulkExport\Controller\Admin;

use BulkExport\Api\Representation\ExporterRepresentation;
use BulkExport\Form\ExporterDeleteForm;
use BulkExport\Form\ExporterForm;
use BulkExport\Form\ExporterStartForm;
use BulkExport\Job\Export as JobExport;
use Common\Stdlib\PsrMessage;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;

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
        try {
            $exporter = $id ? $this->api()->read('bulk_exporters', ['id' => $id])->getContent() : null;
        } catch (\Exception $e) {
            $exporter = null;
        }

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
                    $oConfig = array_replace(['exporter' => [], 'formatter' => []], $currentData['o:config']);
                    $oConfig['exporter'] = $data['o:config']['exporter'] ?? [];
                    $data['o:config'] = $oConfig;
                    $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $data, [], ['isPartial' => true]);
                }

                if (!$response) {
                    $this->messenger()->addError((new PsrMessage(
                        'Save of exporter {exporter_label} failed', // @translate
                        ['exporter_label' => $response->getContent()->linkPretty()]
                    ))->setEscapeHtml(false));
                    return $id
                        ? $this->redirect()->toRoute('admin/bulk-export/id', [], true)
                        : $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
                } else {
                    $exporter = $response->getContent();
                    $this->messenger()->addSuccess((new PsrMessage(
                        'Exporter {exporter_label} successfully saved', // @translate
                        ['exporter_label' => $exporter->linkPretty()]
                    ))->setEscapeHtml(false));
                    return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
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
        try {
            $exporter = $id ? $this->api()->read('bulk_exporters', ['id' => $id])->getContent() : null;
        } catch (\Exception $e) {
            $exporter = null;
        }

        if (!$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
        }

        // Check if the exporter has exports.
        // Don't load entities if the only information needed is total results.
        $total = $this->api()->search('bulk_exports', ['exporter_id' => $id, 'limit' => 0])->getTotalResults();
        if ($total) {
            $this->messenger()->addWarning('This exporter cannot be deleted: exports that use it exist.'); // @translate
            return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
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
                return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'exporter' => $exporter,
            'form' => $form,
        ]);
    }

    public function configureFormatterAction()
    {
        /** @var \BulkExport\Api\Representation\ExporterRepresentation $exporter */
        $id = (int) $this->params()->fromRoute('id');
        try {
            $exporter = $id ? $this->api()->read('bulk_exporters', ['id' => $id])->getContent() : null;
        } catch (\Exception $e) {
            $exporter = null;
        }

        if (!$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
        }

        // Get form class from config registry.
        $configFormClass = $exporter->getConfigFormClass();
        if (!$configFormClass) {
            $message = new PsrMessage('No configuration form available for formatter "{formatter}"', ['formatter' => $exporter->formatterName() ?? 'N/A']); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
        }

        $form = $this->getForm($configFormClass);
        $form->setAttribute('id', 'exporter-formatter-form');

        // Get current config from exporter.
        $formatterConfig = $exporter->formatterConfig();
        $form->setData($formatterConfig);

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
                // Extract form data directly (no Writer needed).
                $formData = $form->getData();
                unset($formData['csrf'], $formData['form_submit'], $formData['current_form']);

                $currentData = $exporter->getJsonLd();
                $currentData['o:config']['formatter'] = $formData;
                $update = ['o:config' => $currentData['o:config']];
                $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $update, [], ['isPartial' => true]);
                if ($response) {
                    $this->messenger()->addSuccess((new PsrMessage(
                        'Configuration for exporter {exporter_label} successfully saved', // @translate
                        ['exporter_label' => $exporter->linkPretty()]
                    ))->setEscapeHtml(false));
                } else {
                    $this->messenger()->addError((new PsrMessage(
                        'Save of configuration for exporter {exporter_label} failed', // @translate
                        ['exporter_label' => $exporter->linkPretty()]
                    ))->setEscapeHtml(false));
                }
                return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'exporter' => $exporter,
            'form' => $form,
        ]);
    }

    /**
     * Process a bulk export by step: params and confirm.
     *
     * @todo Simplify code of this multi-steps process.
     * @todo Move to ExportController.
     *
     * @return \Laminas\Http\Response|\Laminas\View\Model\ViewModel
     */
    public function startAction()
    {
        $id = (int) $this->params()->fromRoute('id');

        /** @var \BulkExport\Api\Representation\ExporterRepresentation $exporter */
        try {
            $exporter = $id ? $this->api()->read('bulk_exporters', ['id' => $id])->getContent() : null;
        } catch (\Exception $e) {
            $exporter = null;
        }

        if (!$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
        }

        // Check formatter exists.
        $formatterName = $exporter->formatterName();
        if (!$formatterName) {
            $message = new PsrMessage('Formatter is not configured for this exporter'); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
        }

        /** @var \Laminas\Session\SessionManager $sessionManager */
        $sessionManager = Container::getDefaultManager();
        $session = new Container('BulkExport', $sessionManager);

        if (!$this->getRequest()->isPost()) {
            $session->exchangeArray([]);
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
                return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
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
                    case 'formatter':
                        // Extract params directly from form data.
                        $session->comment = trim((string) ($data['comment'] ?? ''));
                        $session->useBackground = !empty($data['use_background']);
                        $session->formatterParams = $data;
                        $next = 'confirm';
                        $formCallback = $formsCallbacks[$next];
                        break;

                    case 'confirm':
                        $exportData = [];
                        $exportData['o:owner'] = $this->identity();
                        $exportData['o-bulk:comment'] = trim((string) $session['comment']) ?: null;
                        $exportData['o-bulk:exporter'] = $exporter->getResource();

                        // Get params from session.
                        $formatterParams = $session->formatterParams ?? [];

                        // Add some default params.
                        $formatterParams['site_slug'] = null;
                        $formatterParams['is_site_request'] = false;

                        $exportData['o:params'] = [
                            'formatter' => $formatterParams,
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
                            return $this->redirect()->toRoute('admin/bulk-export', ['action' => 'browse'], true);
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

                        return $this->redirect()->toRoute('admin/bulk-export', [], true);
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
            'step' => $next ?? 'formatter',
            'steps' => array_keys(array_filter($formsCallbacks)),
        ]);

        if ($next === 'confirm') {
            $exportArgs = [];
            $exportArgs['comment'] = $session['comment'];
            $exportArgs['formatter'] = $session['formatterParams'];
            // For security purpose.
            unset($exportArgs['formatter']['filename']);
            unset($exportArgs['formatter']['export_id']);
            unset($exportArgs['formatter']['exporter_label']);
            unset($exportArgs['formatter']['export_started']);
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

        // Get params form class from config registry.
        $paramsFormClass = $exporter->getParamsFormClass();
        if ($paramsFormClass) {
            /* @return \Laminas\Form\Form */
            $formsCallbacks['formatter'] = function () use ($exporter, $controller, $paramsFormClass) {
                $formatterForm = $controller->getForm($paramsFormClass);
                // Pre-fill with exporter's saved config.
                $formatterConfig = $exporter->formatterConfig();
                $formatterForm->setData($formatterConfig);

                $formatterForm->add([
                    'name' => 'current_form',
                    'type' => Element\Hidden::class,
                    'attributes' => [
                        'value' => 'formatter',
                    ],
                ]);
                $formatterForm->add([
                    'name' => 'form_submit',
                    'type' => Fieldset::class,
                ]);
                $formatterForm->get('form_submit')->add([
                    'name' => 'submit',
                    'type' => Element\Submit::class,
                    'attributes' => [
                        'value' => 'Continue', // @translate
                    ],
                ]);

                return $formatterForm;
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
