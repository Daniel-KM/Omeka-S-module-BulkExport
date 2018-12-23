<?php
namespace Import\Controller\Admin;

use Import\Form\ImporterDeleteForm;
use Import\Form\ImporterForm;
use Import\Form\ImporterStartForm;
use Import\Interfaces\Parametrizable;
use Import\Interfaces\Processor;
use Import\Job\Import as JobImport;
use Import\Traits\ServiceLocatorAwareTrait;
use Omeka\Media\Ingester\Manager as MediaIngesterManager ;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Session\Container;
use Zend\Session\SessionManager;
use Zend\View\Model\ViewModel;

class ImporterController extends AbstractActionController
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
        if ($entity) {
            $form->setData($entity->getJsonLd());
        }

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                if ($entity) {
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
        $view->setVariable('form', $form);
        return $view;
    }

    protected function getStartFormsCallbacks($importer)
    {
        $controller = $this;
        $formsCallbacks = [];

        $reader = $importer->getReader();
        if ($reader instanceof Parametrizable) {
            $formsCallbacks['reader'] = function () use ($reader, $controller) {
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
            $formsCallbacks['processor'] = function () use ($processor, $controller) {
                $processorForm = $controller->getForm($processor->getParamsFormClass(), [
                    'processor' => $processor,
                ]);
                $processorConfig = ($processor->getConfig())
                    ? $processor->getConfig()
                    : [];
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

        $formsCallbacks['start'] = function () use ($controller) {
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
