<?php declare(strict_types=1);

namespace BulkExport\Controller\Admin;

use BulkExport\Api\Representation\ExporterRepresentation;
use BulkExport\Form\ExporterDeleteForm;
use BulkExport\Form\ExporterForm;
use BulkExport\Form\ExporterStartForm;
use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Job\Export as JobExport;
use BulkExport\Traits\ServiceLocatorAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;
use Log\Stdlib\PsrMessage;

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
            return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
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
                    return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
                } else {
                    $this->messenger()->addError('Save of exporter failed'); // @translate
                    return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'form' => $form,
        ]);
    }

    public function deleteAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        $entity = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
        }

        // Check if the exporter has exports.
        // Don't load entities if the only information needed is total results.
        $total = $this->api()->search('bulk_exports', ['exporter_id' => $id, 'limit' => 0])->getTotalResults();
        if ($total) {
            $this->messenger()->addWarning('This exporter cannot be deleted: exports that use it exist.'); // @translate
            return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
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
                    return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
                } else {
                    $this->messenger()->addError('Delete of exporter failed'); // @translate
                    return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'entity' => $entity,
            'form' => $form,
        ]);
    }

    public function configureWriterAction()
    {
        $id = (int) $this->params()->fromRoute('id');
        $entity = ($id) ? $this->api()->searchOne('bulk_exporters', ['id' => $id])->getContent() : null;

        if (!$entity) {
            $message = new PsrMessage('Exporter #{exporter_id} does not exist', ['exporter_id' => $id]); // @translate
            $this->messenger()->addError($message);
            return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
        }

        $writer = $entity->writer();
        $form = $this->getForm($writer->getConfigFormClass());
        $writerConfig = $writer instanceof Configurable ? $writer->getConfig() : [];
        $form->setData($writerConfig);

        $form->add([
            'name' => 'exporter_submit',
            'type' => Fieldset::class,
        ]);
        $form->get('exporter_submit')->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'value' => 'Save', // @translate
                'id' => 'submitbutton',
            ],
        ]);

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
            if ($form->isValid()) {
                $writer->handleConfigForm($form);
                $data['writer_config'] = $writer->getConfig();
                $response = $this->api($form)->update('bulk_exporters', $this->params('id'), $data, [], ['isPartial' => true]);

                if ($response) {
                    $this->messenger()->addSuccess('Writer configuration saved'); // @translate
                    return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
                } else {
                    $this->messenger()->addError('Save of writer configuration failed'); // @translate
                    return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return new ViewModel([
            'writer' => $writer,
            'form' => $form,
        ]);
    }

    /**
     * @todo Simplify code of this three steps process.
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
            return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
        }

        $writer = $exporter->writer();

        /** @var \Laminas\Session\SessionManager $sessionManager */
        $sessionManager = Container::getDefaultManager();
        $session = new Container('ExporterStartForm', $sessionManager);

        if (!$this->getRequest()->isPost()) {
            $session->exchangeArray([]);
        }
        if (isset($session->writer)) {
            $writer->setParams($session->writer);
        }

        $formsCallbacks = $this->getStartFormsCallbacks($exporter);
        $formCallback = reset($formsCallbacks);

        $next = null;
        if ($this->getRequest()->isPost()) {
            // Current form.
            $currentForm = $this->getRequest()->getPost('current_form');
            $form = call_user_func($formsCallbacks[$currentForm]);

            // Make certain to merge the files info if any!
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
                    case 'writer':
                        $writer->handleParamsForm($form);
                        $session->comment = trim((string) $data['comment']);
                        $session->useBackground = (bool) ($data['use_background']);
                        $session->writer = $writer->getParams();
                        if (!$writer->isValid()) {
                            $this->messenger()->addError($writer->getLastErrorMessage());
                            $next = 'writer';
                        } else {
                            $next = 'start';
                        }
                        $formCallback = $formsCallbacks[$next];
                        break;

                    case 'start':
                        $exportData = [];
                        $exportData['o:owner'] = $this->identity();
                        $exportData['o-module-bulk:comment'] = trim((string) $session['comment']) ?: null;
                        $exportData['o-module-bulk:exporter'] = $exporter->getResource();
                        if ($writer instanceof Parametrizable) {
                            $exportData['o-module-bulk:writer_params'] = $writer->getParams();
                        }

                        $response = $this->api()->create('bulk_exports', $exportData);
                        if (!$response) {
                            $this->messenger()->addError('Save of export failed'); // @translate
                            break;
                        }
                        $export = $response->getContent();

                        $useBackground = $session->useBackground;

                        // Clear export session.
                        $session->exchangeArray([]);

                        $args = [
                            'export_id' => $export->id(),
                            // Save the base url in order to be able to set the
                            // good url for the linked resources and other urls
                            // in the background job.
                            'host' => $this->viewHelpers()->get('ServerUrl')->getHost(),
                            // Save the base url of files in order to be able to
                            // set the good url for the result file.
                            'base_files' => $this->viewHelpers()->get('BasePath')->__invoke('/files'),
                        ];

                        /** @var \Omeka\Job\Dispatcher $dispatcher */
                        $dispatcher = $this->jobDispatcher();
                        try {
                            $job = $useBackground
                                ? $dispatcher->dispatch(JobExport::class, $args)
                                : $dispatcher->dispatch(JobExport::class, $args, $this->getServiceLocator()->get(\Omeka\Job\DispatchStrategy\Synchronous::class));
                            $urlHelper = $this->url();
                            $message = $useBackground
                                ? 'Export started in background (job {link_open_job}#{jobId}{link_close}, {link_open_log}logs{link_close}). This may take a while.' // @translate
                                : 'Export processed in (job {link_open_job}#{jobId}{link_close}, {link_open_log}logs{link_close}).'; // @translate
                            $message = new PsrMessage(
                                $message,
                                [
                                    'link_open_job' => sprintf(
                                        '<a href="%s">',
                                        htmlspecialchars($urlHelper->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                                    ),
                                    'jobId' => $job->getId(),
                                    'link_close' => '</a>',
                                    'link_open_log' => sprintf(
                                        '<a href="%s">',
                                        htmlspecialchars($urlHelper->fromRoute('admin/bulk-export/id', ['controller' => 'export', 'action' => 'logs', 'id' => $export->id()]))
                                    ),
                                ]
                            );
                            $message->setEscapeHtml(false);
                            $this->messenger()->addSuccess($message);
                        } catch (\Exception $e) {
                            $this->messenger()->addError('Export start failed'); // @translate
                        }

                        return $this->redirect()->toRoute('admin/bulk-export/default', ['controller' => 'bulk-export']);
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

        $view = new ViewModel([
            'exporter' => $exporter,
            'form' => $form,
        ]);
        if ($next === 'start') {
            $exportArgs = [];
            $exportArgs['comment'] = $session['comment'];
            $exportArgs['writer'] = $session['writer'];
            // For security purpose.
            unset($exportArgs['writer']['filename']);
            $view->setVariable('exportArgs', $exportArgs);
        }
        return $view;
    }

    protected function getStartFormsCallbacks(ExporterRepresentation $exporter)
    {
        $controller = $this;
        $formsCallbacks = [];

        $writer = $exporter->writer();
        if ($writer instanceof Parametrizable) {
            /* @return \Laminas\Form\Form */
            $formsCallbacks['writer'] = function () use ($writer, $controller) {
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
                    'name' => 'writer_submit',
                    'type' => Fieldset::class,
                ]);
                $writerForm->get('writer_submit')->add([
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
        $formsCallbacks['start'] = function () use ($controller) {
            $startForm = $controller->getForm(ExporterStartForm::class);
            $startForm->add([
                'name' => 'current_form',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'value' => 'start',
                ],
            ]);
            return $startForm;
        };

        return $formsCallbacks;
    }
}
