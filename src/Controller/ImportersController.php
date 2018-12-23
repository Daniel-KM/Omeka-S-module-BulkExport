<?php
namespace Import\Controller;

use Import\Job\Import as JobImport;
use Import\Form\ImporterDeleteForm;
use Import\Form\ImporterForm;
use Import\Form\ImporterStartForm;
use Import\Interfaces\Parametrizable;
use Import\Interfaces\Processor;

use Import\Traits\ServiceLocatorAwareTrait;
use Omeka\Media\Ingester\Manager as MediaIngesterManager ;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Model\ViewModel;
use Zend\Session\SessionManager;
use Zend\Session\Container;

class ImportersController extends AbstractActionController
{
    use ServiceLocatorAwareTrait;

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
        $entity = ($id) ? $this->api()->searchOne('import_importers', ['id' => $id])->getContent() : null;

        if ($id && !$entity) {
            $this->messenger()->addError(sprintf('Importer with id %s does not exist', $id));
            return $this->redirect()->toRoute('admin/import');
        }

        $form = $this->getForm(ImporterForm::class);
        if($entity) $form->setData($entity->getJsonLd());

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {

                if($entity) {
                    $response = $this->api($form)->update('import_importers', $this->params('id'), $data, [], ['isPartial' => true]);
                } else {
                    $response = $this->api($form)->create('import_importers', $data);
                }

                if ($response) {
                    $this->messenger()->addSuccess('Importer successfully saved');
                    return $this->redirect()->toRoute('admin/import');
                } else {
                    $this->messenger()->addError('Save of importer failed ');
                    return $this->redirect()->toRoute('admin/import');
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
        $entity = ($id) ? $this->api()->searchOne('import_importers', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $this->messenger()->addError(sprintf('Importer with id %s does not exist', $id));
            return $this->redirect()->toRoute('admin/import');
        }

        $form = $this->getForm(ImporterDeleteForm::class);
        $form->setData($entity->getJsonLd());

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $response = $this->api($form)->delete('import_importers', $id);
                if ($response) {
                    $this->messenger()->addSuccess('Importer successfully deleted');
                    return $this->redirect()->toRoute('admin/import');
                } else {
                    $this->messenger()->addError('Delete of importer failed');
                    return $this->redirect()->toRoute('admin/import');
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
        $entity = ($id) ? $this->api()->searchOne('import_importers', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $this->messenger()->addError(sprintf('Importer with id %s does not exist', $id));
            return $this->redirect()->toRoute('admin/import');
        }

        $reader = $entity->getReader();
        $form = $this->getForm($reader->getConfigFormClass());
        $readerConfig = ($reader->getConfig()) ? $reader->getConfig() : [];
        $form->setData($readerConfig);

        $form->add([
            'name' => 'importer_submit',
            'type' => 'fieldset',
        ]);
        $form->get('importer_submit')->add([
            'type'  => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Save',
                'id' => 'submitbutton',
            ],
        ]);

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {

                $reader->handleConfigForm($form);
                $data['reader_config'] = $reader->getConfig();
                $response = $this->api($form)->update('import_importers', $this->params('id'), $data, [], ['isPartial' => true]);

                if ($response) {
                    $this->messenger()->addSuccess('Reader configuration saved');
                    return $this->redirect()->toRoute('admin/import');
                } else {
                    $this->messenger()->addError('Save of reader configuration failed');
                    return $this->redirect()->toRoute('admin/import');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function configureProcessorAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        $entity = ($id) ? $this->api()->searchOne('import_importers', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $this->messenger()->addError(sprintf('Importer with id %s does not exist', $id));
            return $this->redirect()->toRoute('admin/import');
        }

        /** @var Processor $processor */
        $processor = $entity->getProcessor();
        $form = $this->getForm($processor->getConfigFormClass());
        $processorConfig = ($processor->getConfig()) ? $processor->getConfig() : [];
        $form->setData($processorConfig);

        $form->add([
            'name' => 'importer_submit',
            'type' => 'fieldset',
        ]);
        $form->get('importer_submit')->add([
            'type'  => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Save',
                'id' => 'submitbutton',
            ],
        ]);

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $processor->handleConfigForm($form);

                $update = ['processor_config' => $processor->getConfig()];
                $response = $this->api($form)->update('import_importers', $this->params('id'), $update, [], ['isPartial' => true]);

                if ($response) {
                    $this->messenger()->addSuccess('Processor configuration saved');
                    return $this->redirect()->toRoute('admin/import');
                } else {
                    $this->messenger()->addError('Save of processor configuration failed');
                    return $this->redirect()->toRoute('admin/import');
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    public function startAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        $importer = ($id) ? $this->api()->searchOne('import_importers', ['id' => $id])->getContent() : null;

        if (!$importer) {
            $this->messenger()->addError(sprintf('Importer with id %s does not exist', $id));
            return $this->redirect()->toRoute('admin/import');
        }

        $reader = $importer->getReader();
        $processor = $importer->getProcessor();
        $processor->setReader($reader);

        /** @var SessionManager $sessionManager */
        $sessionManager = Container::getDefaultManager();
        /** @var Container $session */
        $session = new Container('ImporterStartForm', $sessionManager);

        if (!$this->getRequest()->isPost()) {
            $session->exchangeArray([]);
        }
        if (isset($session->reader)) {
            $reader->setParams($session->reader);
        }
        if (isset($session->processor)) {
            $processor->setParams($session->processor);
        }

        $formsCallbacks = $this->getStartFormsCallbacks($importer);
        $formCallback = reset($formsCallbacks);

        if ($this->getRequest()->isPost()) {
            //current form
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

                switch($currentForm) {
                    default:
                    case 'reader':
                        $reader->handleParamsForm($form);
                        $session->reader = $reader->getParams();
                        $formCallback = isset($formsCallbacks['processor']) ? $formsCallbacks['processor'] : $formsCallbacks['start'];
                    break;

                    case 'processor':
                        $processor->handleParamsForm($form);
                        $session->processor = $processor->getParams();
                        $formCallback = $formsCallbacks['start'];
                    break;

                    case 'start':
                        $importData = [
                            'status' => 'queued',
                            'importer' => $importer->getResource(),
                        ];
                        if ($reader instanceof Parametrizable) {
                            $importData['reader_params'] = $reader->getParams();
                        }
                        if ($processor instanceof Parametrizable) {
                            $importData['processor_params'] = $processor->getParams();
                        }

                        $response = $this->api()->create('import_imports', $importData);
                        if (!$response) {
                            $this->messenger()->addError('Save of import failed');
                            break;
                        }
                        $import = $response->getContent();

                        //clear import session
                        $session->exchangeArray([]);

                        $dispatcher = $this->jobDispatcher();
                        try {
                            //$dispatcher->dispatch(JobImport::class, ['import_id' => $import->getId()], $this->getServiceLocator()->get('Omeka\Job\DispatchStrategy\Synchronous'));
                            $dispatcher->dispatch(JobImport::class, ['import_id' => $import->getId()]);
                            $this->messenger()->addSuccess('Import started');
                        } catch (\Exception $e) {
                            $this->messenger()->addError('Import start failed');
                        }

                        return $this->redirect()->toRoute('admin/import');
                    break;
                }

                //next form
                $form = call_user_func($formCallback);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        //default form
        if(!isset($form)) {
            $form = call_user_func($formCallback);
        }
        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;

//        $db = $this->getHelper('db');
//
//        $importer = $db->getTable('Import_Importer')->find($this->getParam('id'));
//        $reader = $importer->getReader();
//        $processor = $importer->getProcessor();
//        $processor->setReader($reader);
//
//        $session = new Zend_Session_Namespace('ImporterStartForm');
//        if (!$this->getRequest()->isPost()) {
//            $session->unsetAll();
//        }
//        if (isset($session->reader)) {
//            $reader->setParams($session->reader);
//        }
//        if (isset($session->processor)) {
//            $processor->setParams($session->processor);
//        }
//
//        $formsCallbacks = $this->getStartFormsCallbacks($importer);
//        $formCallback = reset($formsCallbacks);
//
//        if ($this->getRequest()->isPost()) {
//            $currentForm = $this->getRequest()->getPost('current_form');
//            $form = call_user_func($formsCallbacks[$currentForm]);
//            if ($form->isValid($_POST)) {
//                $values = $form->getValues();
//                $session->{$currentForm} = $values;
//                if ($currentForm == 'reader') {
//                    $reader->handleParamsForm($form);
//                    $session->reader = $reader->getParams();
//                    $formCallback = isset($formsCallbacks['processor']) ? $formsCallbacks['processor'] : $formsCallbacks['start'];
//                } elseif ($currentForm == 'processor') {
//                    $processor->handleParamsForm($form);
//                    $session->processor = $processor->getParams();
//                    $formCallback = $formsCallbacks['start'];
//                } elseif ($currentForm == 'start') {
//                    $import = new Import_Import;
//                    $import->importer_id = $importer->id;
//                    if ($reader instanceof Import_Parametrizable) {
//                        $import->setReaderParams($reader->getParams());
//                    }
//                    if ($processor instanceof Import_Parametrizable) {
//                        $import->setProcessorParams($processor->getParams());
//                    }
//                    $import->status = 'queued';
//                    $import->save();
//                    $session->unsetAll();
//
//                    $jobDispatcher = Zend_Registry::get('job_dispatcher');
//                    $jobDispatcher->setQueueName('import_imports');
//                    try {
//                        $jobDispatcher->sendLongRunning('Import_Job_Import', array(
//                            'importId' => $import->id,
//                        ));
//                        $this->flash('Import started');
//                    } catch (Exception $e) {
//                        $import->status = 'error';
//                        $this->flash('Import start failed', 'error');
//                    }
//
//                    $this->redirect('import');
//                }
//            } else {
//                $this->flash(__('Form is invalid'), 'error');
//                foreach ($form->getMessages() as $messages) {
//                    foreach ($messages as $message) {
//                        $this->flash($message, 'error');
//                    }
//                }
//            }
//        }
//
//        $form = call_user_func($formCallback);
//        $this->view->form = $form;
    }

    protected function getStartFormsCallbacks($importer)
    {
        $controller = $this;
        $formsCallbacks = array();

        $reader = $importer->getReader();
        if ($reader instanceof Parametrizable) {
            $formsCallbacks['reader'] = function() use($reader, $controller) {
                $readerForm = $controller->getForm($reader->getParamsFormClass());
                $readerConfig = ($reader->getConfig()) ? $reader->getConfig() : [];
                $readerForm->setData($readerConfig);

                $readerForm->add([
                    'type'  => 'hidden',
                    'name' => 'current_form',
                    'attributes' => [
                        'value' => 'reader',
                    ],
                ]);
                $readerForm->add([
                    'name' => 'reader_submit',
                    'type' => 'fieldset',
                ]);
                $readerForm->get('reader_submit')->add([
                    'type'  => 'submit',
                    'name' => 'submit',
                    'attributes' => [
                        'value' => 'Continue',
                    ],
                ]);

                return $readerForm;
            };
        }

        $processor = $importer->getProcessor();
        $processor->setReader($reader);
        if ($processor instanceof Parametrizable) {
            $formsCallbacks['processor'] = function() use($processor, $controller) {
                $processorForm = $controller->getForm($processor->getParamsFormClass(), [
                    'processor' => $processor,
                ]);
                $processorConfig = ($processor->getConfig()) ? $processor->getConfig() : [];
                $processorForm->setData($processorConfig);

                $processorForm->add([
                    'type'  => 'hidden',
                    'name' => 'current_form',
                    'attributes' => [
                        'value' => 'processor',
                    ],
                ]);
                $processorForm->add([
                    'name' => 'reader_submit',
                    'type' => 'fieldset',
                ]);
                $processorForm->get('reader_submit')->add([
                    'type'  => 'submit',
                    'name' => 'submit',
                    'attributes' => [
                        'value' => 'Continue',
                    ],
                ]);

                return $processorForm;
            };
        }

        $formsCallbacks['start'] = function() use($controller) {
            $startForm = $controller->getForm(ImporterStartForm::class);
            $startForm->add([
                'type'  => 'hidden',
                'name' => 'current_form',
                'attributes' => [
                    'value' => 'start',
                ],
            ]);
            return $startForm;
        };

        return $formsCallbacks;
    }
}
