<?php
namespace BulkExport\Controller\Admin;

use BulkExport\Api\Representation\ExporterRepresentation;
use BulkExport\Form\ExporterDeleteForm;
use BulkExport\Form\ExporterForm;
use BulkExport\Form\ExporterStartForm;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Job\Export as JobExport;
use BulkExport\Traits\ServiceLocatorAwareTrait;
use Log\Stdlib\PsrMessage;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Session\Container;
use Zend\View\Model\ViewModel;

class ExporterController extends AbstractActionController
{
    use ServiceLocatorAwareTrait;

    /**
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
    }

    public function addAction()
    {
        return $this->editAction();
    }

    public function editAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        /** @var \BulkExport\Api\Representation\ExporterRepresentation $entity */
        $entity = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if ($id && !$entity) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk');
        }

        $form = $this->getForm(ExporterForm::class);
        if ($entity) {
            $data = $entity->getJsonLd();
            $form->setData($data);
        }

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                if ($entity) {
                    $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $data, [], ['isPartial' => true]);
                } else {
                    $data['o:owner'] = $this->identity();
                    $response = $this->api($form)->create('bulk_exporters', $data);
                }

                if ($response) {
                    $this->messenger()->addSuccess('Exporter successfully saved'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                } else {
                    $this->messenger()->addError('Save of exporter failed'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function deleteAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        $entity = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk');
        }

        // Check if the exporter has exports.
        $total = $this->api()->search('bulk_exports', ['exporter_id' => $id])->getTotalResults();
        if ($total) {
            $this->messenger()->addWarning('This exporterd cannot be deleted: exports that use it exist.'); // @translate
            return $this->redirect()->toRoute('admin/bulk');
        }

        $form = $this->getForm(ExporterDeleteForm::class);
        $form->setData($entity->getJsonLd());

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->delete('bulk_exporters', $id);
                if ($response) {
                    $this->messenger()->addSuccess('Exporter successfully deleted'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                } else {
                    $this->messenger()->addError('Delete of exporter failed'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('entity', $entity);
        $view->setVariable('form', $form);
        return $view;
    }

    public function configureReaderAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        $entity = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk');
        }

        $reader = $entity->reader();
        $form = $this->getForm($reader->getConfigFormClass());
        $readerConfig = ($reader->getConfig()) ? $reader->getConfig() : [];
        $form->setData($readerConfig);

        $form->add([
            'name' => 'exporter_submit',
            'type'  => Fieldset::class,
        ]);
        $form->get('exporter_submit')->add([
            'name' => 'submit',
            'type'  => Element\Submit::class,
            'attributes' => [
                'value' => 'Save', // @translate
                'id' => 'submitbutton',
            ],
        ]);

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $reader->handleConfigForm($form);
                $data['reader_config'] = $reader->getConfig();
                $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $data, [], ['isPartial' => true]);

                if ($response) {
                    $this->messenger()->addSuccess('Reader configuration saved'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                } else {
                    $this->messenger()->addError('Save of reader configuration failed'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('reader', $reader);
        $view->setVariable('form', $form);
        return $view;
    }

    public function configureProcessorAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        $entity = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk');
        }

        /** @var Processor $processor */
        $processor = $entity->processor();
        $form = $this->getForm($processor->getConfigFormClass());
        $processorConfig = ($processor->getConfig()) ? $processor->getConfig() : [];
        $form->setData($processorConfig);

        $form->add([
            'name' => 'exporter_submit',
            'type'  => Fieldset::class,
        ]);
        $form->get('exporter_submit')->add([
            'name' => 'submit',
            'type'  => Element\Submit::class,
            'attributes' => [
                'value' => 'Save', // @translate
                'id' => 'submitbutton',
            ],
        ]);

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $processor->handleConfigForm($form);

                $update = ['processor_config' => $processor->getConfig()];
                $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $update, [], ['isPartial' => true]);

                if ($response) {
                    $this->messenger()->addSuccess('Processor configuration saved'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                } else {
                    $this->messenger()->addError('Save of processor configuration failed'); // @translate
                    return $this->redirect()->toRoute('admin/bulk');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('processor', $processor);
        $view->setVariable('form', $form);
        return $view;
    }

    public function startAction()
    {
        $id = (int) $this->params()->fromRoute('id');

        /** @var \BulkExport\Api\Representation\ExporterRepresentation $exporter */
        $exporter = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;
        if (!$exporter) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk');
        }

        $reader = $exporter->reader();
        $processor = $exporter->processor();
        $processor->setReader($reader);

        /** @var \Zend\Session\SessionManager $sessionManager */
        $sessionManager = Container::getDefaultManager();
        $session = new Container('ExporterStartForm', $sessionManager);

        if (!$this->getRequest()->isPost()) {
            $session->exchangeArray([]);
        }
        if (isset($session->reader)) {
            $reader->setParams($session->reader);
        }
        if (isset($session->processor)) {
            $processor->setParams($session->processor);
        }

        $formsCallbacks = $this->getStartFormsCallbacks($exporter);
        $formCallback = reset($formsCallbacks);

        $next = null;
        if ($this->getRequest()->isPost()) {
            // Current form.
            $currentForm = $this->getRequest()->getPost('current_form');
            $form = call_user_func($formsCallbacks[$currentForm]);

            // Make certain to merge the files info!
            $request = $this->getRequest();
            $data = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            // Pass data to form.
            $form->setData($data);
            if ($form->isValid()) {
                // Execute file filters.
                $data = $form->getData();
                $session->{$currentForm} = $data;
                switch ($currentForm) {
                    default:
                    case 'reader':
                        $reader->handleParamsForm($form);
                        $session->reader = $reader->getParams();
                        if (!$reader->isValid()) {
                            $this->messenger()->addError($reader->getLastErrorMessage());
                            $next = 'reader';
                        } else {
                            $next = isset($formsCallbacks['processor']) ? 'processor' : 'start';
                        }
                        $formCallback = $formsCallbacks[$next];
                        break;

                    case 'processor':
                        $processor->handleParamsForm($form);
                        $session->processor = $processor->getParams();
                        $next = 'start';
                        $formCallback = $formsCallbacks['start'];
                        break;

                    case 'start':
                        $exportData = [];
                        $exportData['o-module-bulk:exporter'] = $exporter->getResource();
                        if ($reader instanceof Parametrizable) {
                            $exportData['o-module-bulk:reader_params'] = $reader->getParams();
                        }
                        if ($processor instanceof Parametrizable) {
                            $exportData['o-module-bulk:processor_params'] = $processor->getParams();
                        }

                        $response = $this->api()->create('bulk_exports', $exportData);
                        if (!$response) {
                            $this->messenger()->addError('Save of export failed'); // @translate
                            break;
                        }
                        $export = $response->getContent();

                        // Clear export session.
                        $session->exchangeArray([]);

                        $args = ['export_id' => $export->id()];

                        $dispatcher = $this->jobDispatcher();
                        try {
                            // Synchronous dispatcher for testing purpose.
                            // $job = $dispatcher->dispatch(JobExport::class, $args, $this->getServiceLocator()->get('Omeka\Job\DispatchStrategy\Synchronous'));
                            $job = $dispatcher->dispatch(JobExport::class, $args);

                            $message = new PsrMessage(
                                'Export started in background (<a href="{job_url}">job #{job_id}</a>). This may take a while.', // @translate
                                [
                                    'job_url' => htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()])),
                                    'job_id' => $job->getId(),
                                ]
                            );
                            $message->setEscapeHtml(false);
                            $this->messenger()->addSuccess($message);
                        } catch (\Exception $e) {
                            $this->messenger()->addError('Export start failed'); // @translate
                        }

                        return $this->redirect()->toRoute('admin/bulk');
                        break;
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

        $view = new ViewModel;
        $view->setVariable('exporter', $exporter);
        $view->setVariable('form', $form);
        if ($next === 'start') {
            $exportArgs = [];
            $exportArgs['reader'] = $session['reader'];
            $exportArgs['processor'] = $currentForm === 'reader' ? [] : $session['processor'];
            // For security purpose.
            unset($exportArgs['reader']['filename']);
            $view->setVariable('exportArgs', $exportArgs);
        }
        return $view;
    }

    protected function getStartFormsCallbacks(ExporterRepresentation $exporter)
    {
        $controller = $this;
        $formsCallbacks = [];

        $reader = $exporter->reader();
        if ($reader instanceof Parametrizable) {
            /** @return \Zend\Form\Form */
            $formsCallbacks['reader'] = function () use ($reader, $controller) {
                $readerForm = $controller->getForm($reader->getParamsFormClass());
                $readerConfig = $reader->getConfig() ?: [];
                $readerForm->setData($readerConfig);

                $readerForm->add([
                    'name' => 'current_form',
                    'type'  => Element\Hidden::class,
                    'attributes' => [
                        'value' => 'reader',
                    ],
                ]);
                $readerForm->add([
                    'name' => 'reader_submit',
                    'type'  => Fieldset::class,
                ]);
                $readerForm->get('reader_submit')->add([
                    'name' => 'submit',
                    'type'  => Element\Submit::class,
                    'attributes' => [
                        'value' => 'Continue', // @translate
                    ],
                ]);

                return $readerForm;
            };
        }

        $processor = $exporter->processor();
        $processor->setReader($reader);
        if ($processor instanceof Parametrizable) {
            /** @return \Zend\Form\Form */
            $formsCallbacks['processor'] = function () use ($processor, $controller) {
                $processorForm = $controller->getForm($processor->getParamsFormClass(), [
                    'processor' => $processor,
                ]);
                $processorConfig = $processor->getConfig() ?: [];
                $processorForm->setData($processorConfig);

                $processorForm->add([
                    'name' => 'current_form',
                    'type'  => Element\Hidden::class,
                    'attributes' => [
                        'value' => 'processor',
                    ],
                ]);
                $processorForm->add([
                    'name' => 'reader_submit',
                    'type'  => Fieldset::class,
                ]);
                $processorForm->get('reader_submit')->add([
                    'name' => 'submit',
                    'type'  => Element\Submit::class,
                    'attributes' => [
                        'value' => 'Continue', // @translate
                    ],
                ]);

                return $processorForm;
            };
        }

        /** @return \Zend\Form\Form */
        $formsCallbacks['start'] = function () use ($controller) {
            $startForm = $controller->getForm(ExporterStartForm::class);
            $startForm->add([
                'name' => 'current_form',
                'type'  => Element\Hidden::class,
                'attributes' => [
                    'value' => 'start',
                ],
            ]);
            return $startForm;
        };

        return $formsCallbacks;
    }
}
