<?php

namespace BulkExport\Writer;

use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Traits\ConfigurableTrait;
use BulkExport\Traits\ParametrizableTrait;
use BulkExport\Traits\ServiceLocatorAwareTrait;
use Log\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Job\AbstractJob as Job;
use Zend\Form\Form;
use Zend\Log\Logger;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractWriter implements WriterInterface, Configurable, Parametrizable
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
     * var array
     */
    protected $configKeys = [];

    /**
     * var array
     */
    protected $paramsKeys = [];

    /**
     * @var string|null
     */
    protected $lastErrorMessage;

    /**
     * @var int
     */
    protected $totalEntries;

    /**
     * @var string
     */
    protected $filepath;

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
        return $this;
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
        return $this;
    }

    abstract public function process();

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
                return null;
            }
            @mkdir($dirPath, 0755, true);
        } elseif (!is_dir($dirPath) || !is_writeable($dirPath)) {
            $this->logger->err(
                'The destination folder "{folder}" is not writeable.', // @translate
                ['folder' => $basePath . '/' . $dirPath]
            );
            return null;
        }
        return $dirPath;
    }

    protected function prepareTempFile()
    {
        // TODO Use Omeka factory for temp files.
        $config = $this->getServiceLocator()->get('Config');
        $tempDir = $config['temp_dir'] ?: sys_get_temp_dir();
        $this->filepath = tempnam($tempDir, 'omk_export_');
        return $this;
    }

    protected function saveFile()
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
                    $result = copy($this->filepath, $filepath);
                    @unlink($this->filepath);
                } catch (\Exception $e) {
                    throw new \Omeka\Job\Exception\RuntimeException(new PsrMessage(
                        'Export error when saving "{filename}" (temp file: "{tempfile}"): {exception}', // @translate
                        ['filename' => $filename, 'tempfile' => $this->filepath, 'exception' => $e]
                    ));
                }

                if (!$result) {
                    throw new \Omeka\Job\Exception\RuntimeException(new PsrMessage(
                        'Export error when saving "{filename}" (temp file: "{tempfile}").', // @translate
                        ['filename' => $filename, 'tempfile' => $this->filepath]
                    ));
                }

                break;
            }
        } while (++$i);

        $params = $this->getParams();
        $params['filename'] = $filename;
        $this->setParams($params);
        return $this;
    }

    /**
     * @todo Factorize with \BulkExport\Traits\ResourceFieldsTrait::mapResourceTypeToClass()
     * @param string $jsonResourceType
     * @return string|null
     */
    protected function mapResourceTypeToClass($jsonResourceType)
    {
        $mapping = [
            // Core.
            'o:User' => \Omeka\Entity\User::class,
            'o:Vocabulary' => \Omeka\Entity\Vocabulary::class,
            'o:ResourceClass' => \Omeka\Entity\ResourceClass::class,
            'o:ResourceTemplate' => \Omeka\Entity\ResourceTemplate::class,
            'o:Property' => \Omeka\Entity\Property::class,
            'o:Item' => \Omeka\Entity\Item::class,
            'o:Media' => \Omeka\Entity\Media::class,
            'o:ItemSet' => \Omeka\Entity\ItemSet::class,
            'o:Module' => \Omeka\Entity\Module::class,
            'o:Site' => \Omeka\Entity\Site::class,
            'o:SitePage' => \Omeka\Entity\SitePage::class,
            'o:Job' => \Omeka\Entity\Job::class,
            'o:Resource' => \Omeka\Entity\Resource::class,
            'o:Asset' => \Omeka\Entity\Asset::class,
            'o:ApiResource' => null,
            // Modules.
            'oa:Annotation' => \Annotate\Entity\Annotation::class,
        ];
        return isset($mapping[$jsonResourceType]) ? $mapping[$jsonResourceType] : null;
    }

    protected function mapResourceTypeToApiResource($jsonResourceType)
    {
        $mapping = [
            // Core.
            'o:User' => 'users',
            'o:Vocabulary' => 'vocabularies',
            'o:ResourceClass' => 'resource_classes',
            'o:ResourceTemplate' => 'resource_templates',
            'o:Property' => 'properties',
            'o:Item' => 'items',
            'o:Media' => 'media',
            'o:ItemSet' => 'item_sets',
            'o:Module' => 'modules',
            'o:Site' => 'sites',
            'o:SitePage' => 'site_pages',
            'o:Job' => 'jobs',
            'o:Resource' => 'resources',
            'o:Asset' => 'assets',
            'o:ApiResource' => 'api_resources',
            // Modules.
            'oa:Annotation' => 'annotations',
        ];
        return isset($mapping[$jsonResourceType]) ? $mapping[$jsonResourceType] : null;
    }

    protected function mapResourceTypeToText($jsonResourceType)
    {
        static $mapping;
        if (empty($mapping)) {
            $translate = $this->getServiceLocator()->get('ViewHelperManager')->get('translate');
            $mapping = [
                // Core.
                'o:User' => $translate('users'),
                'o:Vocabulary' => $translate('vocabularies'),
                'o:ResourceClass' => $translate('resource classes'),
                'o:ResourceTemplate' => $translate('resource templates'),
                'o:Property' => $translate('properties'),
                'o:Item' => $translate('items'),
                'o:Media' => $translate('media'),
                'o:ItemSet' => $translate('item sets'),
                'o:Module' => $translate('modules'),
                'o:Site' => $translate('sites'),
                'o:SitePage' => $translate('site pages'),
                'o:Job' => $translate('jobs'),
                'o:Resource' => $translate('resources'),
                'o:Asset' => $translate('assets'),
                'o:ApiResource' => $translate('api resources'),
                // Modules.
                'oa:Annotation' => $translate('annotations'),
            ];
        }
        return isset($mapping[$jsonResourceType]) ? $mapping[$jsonResourceType] : null;
    }

    protected function mapResourceTypeToTable($jsonResourceType)
    {
        $mapping = [
            // Core.
            'o:User' => 'user',
            'o:Vocabulary' => 'vocabulary',
            'o:ResourceClass' => 'resource_class',
            'o:ResourceTemplate' => 'resource_template',
            'o:Property' => 'property',
            'o:Item' => 'item',
            'o:Media' => 'media',
            'o:ItemSet' => 'item_set',
            'o:Module' => 'module',
            'o:Site' => 'site',
            'o:SitePage' => 'site_page',
            'o:Job' => 'job',
            'o:Resource' => 'resource',
            'o:Asset' => 'asset',
            'o:ApiResource' => 'api_resource',
            // Modules.
            'oa:Annotation' => 'annotation',
        ];
        return isset($mapping[$jsonResourceType]) ? $mapping[$jsonResourceType] : null;
    }

    protected function mapRepresentationToResourceType(AbstractRepresentation $representation)
    {
        $class = get_class($representation);
        $mapping = [
            // Core.
            \Omeka\Api\Representation\UserRepresentation::class => 'User',
            \Omeka\Api\Representation\VocabularyRepresentation::class => 'Vocabulary',
            \Omeka\Api\Representation\ResourceClassRepresentation::class => 'Resource class',
            \Omeka\Api\Representation\ResourceTemplateRepresentation::class => 'Resource template',
            \Omeka\Api\Representation\PropertyRepresentation::class => 'Property',
            \Omeka\Api\Representation\ItemRepresentation::class => 'Item',
            \Omeka\Api\Representation\MediaRepresentation::class => 'Media',
            \Omeka\Api\Representation\ItemSetRepresentation::class => 'Item set',
            \Omeka\Api\Representation\ModuleRepresentation::class => 'Module',
            \Omeka\Api\Representation\SiteRepresentation::class => 'Site',
            \Omeka\Api\Representation\SitePageRepresentation::class => 'Site page',
            \Omeka\Api\Representation\JobRepresentation::class => 'Job',
            \Omeka\Api\Representation\ResourceReference::class => 'Resource',
            \Omeka\Api\Representation\AssetRepresentation::class => 'Asset',
            \Omeka\Api\Representation\ApiResourceRepresentation::class => 'Api resource',
            // Modules.
            \Annotate\Api\Representation\AnnotationRepresentation::class => 'Annotation',
        ];
        return isset($mapping[$class]) ? $mapping[$class] : null;
    }
}
