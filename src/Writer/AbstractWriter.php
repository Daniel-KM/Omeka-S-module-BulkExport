<?php
namespace BulkExport\Writer;

use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Interfaces\Writer;
use BulkExport\Traits\ConfigurableTrait;
use BulkExport\Traits\ParametrizableTrait;
use BulkExport\Traits\ServiceLocatorAwareTrait;
use Log\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob as Job;
use Zend\Form\Form;
use Zend\Log\Logger;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractWriter implements Writer, Configurable, Parametrizable
{
    use ConfigurableTrait, ParametrizableTrait, ServiceLocatorAwareTrait;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Job
     */
    protected $job;

    /**
     * @var string
     */
    protected $mediaType;

    /**
     * @var string
     */
    protected $configFormClass;

    /**
     * @var string
     */
    protected $paramsFormClass;

    /**
     * @var string|null
     */
    protected $lastErrorMessage;

    /**
     * @var int
     */
    protected $totalEntries;

    /**
     * Writer constructor.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $services)
    {
        $this->setServiceLocator($services);
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function isValid()
    {
        $this->lastErrorMessage = null;
        return true;
    }

    public function getLastErrorMessage()
    {
        return $this->lastErrorMessage;
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;
        return $this;
    }

    public function getConfigFormClass()
    {
        return $this->configFormClass;
    }

    public function handleConfigForm(Form $form)
    {
        $values = $form->getData();
        $config = array_intersect_key($values, array_flip($this->configKeys));
        $this->setConfig($config);
    }

    public function getParamsFormClass()
    {
        return $this->paramsFormClass;
    }

    public function handleParamsForm(Form $form)
    {
        $this->lastErrorMessage = null;
        $values = $form->getData();
        $params = array_intersect_key($values, array_flip($this->paramsKeys));
        $this->setParams($params);
    }

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path.
     * @return string|null
     */
    protected function checkDestinationDir($dirPath)
    {
        if (!file_exists($dirPath)) {
            $config = $this->getServiceLocator()->get('Config');
            $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            if (!is_writeable($basePath)) {
                $this->logger->err(
                    'The destination folder "{folder}" is not writeable.', // @translate
                    ['folder' => $basePath]
                );
                return;
            }
            @mkdir($dirPath, 0755, true);
        } elseif (!is_dir($dirPath) || !is_writeable($dirPath)) {
            $this->logger->err(
                'The destination folder "{folder}" is not writeable.', // @translate
                ['folder' => $basePath . '/' . $dirPath]
            );
            return;
        }
        return $dirPath;
    }

    protected function prepareTempFile()
    {
        // TODO Use Omeka factory for temp files.
        $config = $this->getServiceLocator()->get('Config');
        $tempDir = $config['temp_dir'] ?: sys_get_temp_dir();
        $tempfilepath = tempnam($tempDir, 'omk_export_');
        return $tempfilepath;
    }

    protected function saveFile($tempfilepath)
    {
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $destinationDir = $basePath . '/bulk_export';

        $exporterLabel = $this->getParam('exporter_label', '');
        $base = preg_replace('/[^A-Za-z0-9 ]/', '_', $exporterLabel);
        $base = $base ? preg_replace('/_+/', '_', $base) . '-' : '';
        $date = $this->getParam('export_started', new \DateTime())->format('Ymd-His');
        $extension = $this->getExtension();

        // Avoid issue on very big base.
        $i = 0;
        do {
            $filename = sprintf(
                '%s%s%s.%s',
                $base,
                $date,
                $i ? '-' . $i : '',
                $extension
            );

            $filepath = $destinationDir . '/' . $filename;
            if (!file_exists($filepath)) {
                try {
                    $result = copy($tempfilepath, $filepath);
                    @unlink($tempfilepath);
                } catch (\Exception $e) {
                    throw new \Omeka\Job\Exception\RuntimeException(new PsrMessage(
                        'Export error when saving "{filename}" (temp file: "{tempfile}"): {exception}', // @translate
                        ['filename' => $filename, 'tempfile' => $tempfilepath, 'exception' => $e]
                    ));
                }

                if (!$result) {
                    throw new \Omeka\Job\Exception\RuntimeException(new PsrMessage(
                        'Export error when saving "{filename}" (temp file: "{tempfile}").', // @translate
                        ['filename' => $filename, 'tempfile' => $tempfilepath]
                    ));
                }

                break;
            }
        } while (++$i);

        $params = $this->getParams();
        $params['filename'] = $filename;
        $this->setParams($params);
    }
}
