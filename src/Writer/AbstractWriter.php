<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Traits\ConfigurableTrait;
use BulkExport\Traits\ParametrizableTrait;
use BulkExport\Traits\ServiceLocatorAwareTrait;
use Laminas\Form\Form;
use Laminas\Log\Logger;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Common\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractRepresentation;
use Omeka\Job\AbstractJob as Job;

abstract class AbstractWriter implements WriterInterface, Configurable, Parametrizable
{
    use ConfigurableTrait;
    use ParametrizableTrait;
    use ServiceLocatorAwareTrait;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Omeka\DataType\Manager
     */
    protected $dataTypeManager;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    /**
     * @var \Laminas\View\Renderer\PhpRenderer
     */
    protected $viewRenderer;

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
    protected $paramsKeys = [
        'export_id',
        'exporter_label',
        'export_started',
    ];

    /**
     * @var string
     */
    protected $filepath;

    /**
     * @var bool
     */
    protected $hasHistoryLog = false;

    /**
     * @var null|string
     */
    protected $includeDeleted;

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
     */
    public function __construct(ServiceLocatorInterface $services)
    {
        $this->setServiceLocator($services);
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->translator = $services->get('MvcTranslator');
        $this->easyMeta = $services->get('EasyMeta');
        $this->viewRenderer = $services->get('ViewRenderer');
        $this->dataTypeManager = $services->get('Omeka\DataTypeManager');

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $this->getServiceLocator()->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('HistoryLog');
        $this->hasHistoryLog = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function isValid(): bool
    {
        $this->lastErrorMessage = null;
        $outputPath = $this->getOutputFilepath();
        $destinationDir = dirname($outputPath);
        if (!$this->checkDestinationDir($destinationDir)) {
            $this->lastErrorMessage = new PsrMessage(
                'Output directory "{folder}" is not writeable.', // @translate
                ['folder' => $destinationDir]
            );
            return false;
        }
        return true;
    }

    public function getLastErrorMessage(): ?string
    {
        return isset($this->lastErrorMessage) ? (string) $this->lastErrorMessage : null;
    }

    public function setLogger(Logger $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    public function setJob(Job $job): self
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
        // Include default values in params, but store them only during process.
        $params = [];
        if (isset($values['export_id'])) {
            $params = [
                'export_id' => $values['export_id'],
                'exporter_label' => $values['exporter_label'] ?? null,
                'export_started' => $values['export_started'] ?? null,
            ];
        }
        $params += array_intersect_key($values, array_flip($this->paramsKeys));
        $this->setParams($params);
        return $this;
    }

    abstract public function process(): self;

    /**
     * Check or create the destination folder.
     *
     * @param string $dirPath Absolute path.
     * @return string|null
     */
    protected function checkDestinationDir($dirPath): ?string
    {
        if (strpos($dirPath, '../') !== false || strpos($dirPath, '..\\') !== false) {
            $this->logger->err(
                'The path should not contain "../".', // @translate
                ['folder' => $dirPath]
            );
            return null;
        }
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_writeable($dirPath)) {
                $this->logger->err(
                    'The destination folder "{folder}" is not writeable.', // @translate
                    ['folder' => $dirPath]
                );
                return null;
            }
        } else {
            $result = @mkdir($dirPath, 0775, true);
            if (!$result) {
                $this->logger->err(
                    'The destination folder "{folder}" is not writeable.', // @translate
                    ['folder' => $dirPath]
                );
                return null;
            }
        }
        return $dirPath;
    }

    protected function prepareTempFile(): self
    {
        // TODO Use Omeka factory for temp files.
        $config = $this->getServiceLocator()->get('Config');
        $tempDir = $config['temp_dir'] ?: sys_get_temp_dir();
        $this->filepath = @tempnam($tempDir, 'omk_bke_');
        return $this;
    }

    protected function getOutputFilepath(): string
    {
        static $outputFilepath;

        if (is_string($outputFilepath)) {
            return $outputFilepath;
        }

        // Prepare placeholders.
        $label = $this->getParam('exporter_label') ?? '';
        $label = $this->slugify($label);
        $label = preg_replace('/_+/', '_', $label);
        $exporter = str_replace(['bulkexport', 'writer', '\\'], '', strtolower(get_class($this)));
        $exportId = $this->getExport() ? $this->getExport()->id() : '0';
        $date = (new \DateTime())->format('Ymd');
        $time = (new \DateTime())->format('His');
        /** @var \Omeka\Entity\User $user */
        $user = $this->services->get('Omeka\AuthenticationService')->getIdentity();
        $userId = $user ? $user->getId() : 0;
        $userName = $user
            ? ($this->slugify($user->getName()) ?: $this->translator->translate('unknown')) // @ŧranslate
            : $this->translator->translate('anonymous'); // @ŧranslate

        $placeholders = [
            '{label}' => $label,
            '{exporter}' => $exporter,
            '{export_id}' => $exportId,
            '{date}' => $date,
            '{time}' => $time,
            '{user_id}' => $userId,
            '{username}' => $userName,
            '{random}' => substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(48))), 0, 6),
            // Deprecated.
            '{exportid}' => $exportId,
            '{userid}' => $userId,
        ];

        $config = $this->services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $destinationDir = $basePath . '/bulk_export';

        // The check is done during isValid().
        $dir = null;
        $formatDirPath = $this->getParam('dirpath');
        $hasFormatDirPath = !empty($formatDirPath);
        if ($hasFormatDirPath) {
            $dir = str_replace(array_keys($placeholders), array_values($placeholders), $formatDirPath);
            $dir = trim(rtrim($dir, '/\\ '));
            if (mb_substr($dir, 0, 1) !== '/') {
                $dir = OMEKA_PATH . '/' . $dir;
            }
            if ($dir && $dir !== '/' && $dir !== '\\') {
                $destinationDir = $dir;
            } else {
                $this->logger->warn(
                    'The specified dir path "{path}" is invalid. Using default one.', // @translate
                    ['path' => $formatDirPath]
                );
            }
        }

        $formatFilename = $this->getParam('filebase');
        $hasFormatFilename = !empty($formatFilename);

        $formatFilename = $formatFilename
            ?: ($label ? '{label}-{date}-{time}' : '{exporter}-{date}-{time}');
        $extension = $this->getExtension();

        $base = str_replace(array_keys($placeholders), array_values($placeholders), $formatFilename);
        if (!$base) {
            $base = $this->stranslator->translate('no-name'); // @translate
        }

        // Remove remaining characters in all cases for security and simplicity.
        $base = $this->slugify($base, true);

        // When the filename is specified, no check for overwrite is done.
        // In other cases, avoid to override existing files.
        if ($hasFormatFilename) {
            $outputFilepath = $destinationDir . '/' . $base . '.' . $extension;
        } else {
            // Append an index when needed to avoid issue on very big base.
            $outputFilepath = null;
            $i = 0;
            do {
                $filename = sprintf('%s%s.%s', $base, $i ? '-' . $i : '', $extension);
                $outputFilepath = $destinationDir . '/' . $filename;
            } while (++$i && file_exists($outputFilepath));
        }

        return $outputFilepath;
    }

    /**
     * Transform the given string into a valid filename
     *
     * @see \Omeka\Api\Adapter\SiteSlugTrait::slugify()
     */
    protected function slugify(string $input, bool $keepCase = false): string
    {
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = $transliterator->transliterate($input);
        } elseif (extension_loaded('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        } else {
            $slug = $input;
        }
        $slug = $keepCase ? $slug : mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^a-zA-Z0-9-]+/u', '_', $slug);
        $slug = preg_replace('/-{2,}/', '_', $slug);
        $slug = preg_replace('/-*$/', '', $slug);
        return $slug;
    }

    protected function saveFile(): self
    {
        $config = $this->services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $destinationDir = $basePath . '/bulk_export';

        // When this is the default dir, store only the partial filename.
        $outputFilepath = $this->getOutputFilepath();
        $filename = mb_strpos($outputFilepath, $destinationDir) === 0
            ? mb_substr($outputFilepath, mb_strlen($destinationDir) + 1)
            : $outputFilepath;

        try {
            $result = copy($this->filepath, $outputFilepath);
            @unlink($this->filepath);
        } catch (\Exception $e) {
            throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                'Export error when saving "{filename}" (temp file: "{tempfile}"): {exception}', // @translate
                ['filename' => $filename, 'tempfile' => $this->filepath, 'exception' => $e]
            ));
        }

        if (!$result) {
            throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                'Export error when saving "{filename}" (temp file: "{tempfile}").', // @translate
                ['filename' => $filename, 'tempfile' => $this->filepath]
            ));
        }

        $params = $this->getParams();
        $params['filename'] = $filename;
        $this->setParams($params);
        return $this;
    }

    protected function getExport(): ?ExportRepresentation
    {
        static $export = false;

        if ($export !== false) {
            return $export;
        }

        $exportId = $this->params['export_id'] ?? null;
        if (!$exportId) {
            $export = null;
            return null;
        }

        try {
            $export = $this->api->read('bulk_exports', $exportId)->getContent();
        } catch (\Exception $e) {
            $export = null;
        }

        return $export;
    }

    /**
     * @todo Factorize with \BulkExport\Traits\ResourceFieldsTrait::mapResourceTypeToEntity()
     * @param string $jsonResourceType
     * @return string|null
     */
    protected function mapResourceTypeToEntity($jsonResourceType): ?string
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
        return $mapping[$jsonResourceType] ?? null;
    }

    protected function mapResourceTypeToApiResource($jsonResourceType): ?string
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
        return $mapping[$jsonResourceType] ?? null;
    }

    protected function mapResourceTypeToText($jsonResourceType): ?string
    {
        $mapping = [
            // Core.
            'o:User' => 'users',
            'o:Vocabulary' => 'vocabularies',
            'o:ResourceClass' => 'resource classes',
            'o:ResourceTemplate' => 'resource templates',
            'o:Property' => 'properties',
            'o:Item' => 'items',
            'o:Media' => 'media',
            'o:ItemSet' => 'item sets',
            'o:Module' => 'modules',
            'o:Site' => 'sites',
            'o:SitePage' => 'site pages',
            'o:Job' => 'jobs',
            'o:Resource' => 'resources',
            'o:Asset' => 'assets',
            'o:ApiResource' => 'api resources',
            // Modules.
            'oa:Annotation' => 'annotations',
        ];
        return $mapping[$jsonResourceType] ?? null;
    }

    protected function mapResourceTypeToTable($jsonResourceType): ?string
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
        return $mapping[$jsonResourceType] ?? null;
    }

    protected function mapRepresentationToResourceType(AbstractRepresentation $representation): ?string
    {
        $class = get_class($representation);
        $mapping = [
            // Core.
            \Omeka\Api\Representation\UserRepresentation::class => 'users',
            \Omeka\Api\Representation\VocabularyRepresentation::class => 'vocabularies',
            \Omeka\Api\Representation\ResourceClassRepresentation::class => 'resource_classes',
            \Omeka\Api\Representation\ResourceTemplateRepresentation::class => 'resource_templates',
            \Omeka\Api\Representation\PropertyRepresentation::class => 'properties',
            \Omeka\Api\Representation\ItemRepresentation::class => 'items',
            \Omeka\Api\Representation\MediaRepresentation::class => 'media',
            \Omeka\Api\Representation\ItemSetRepresentation::class => 'item_sets',
            \Omeka\Api\Representation\ModuleRepresentation::class => 'modules',
            \Omeka\Api\Representation\SiteRepresentation::class => 'sites',
            \Omeka\Api\Representation\SitePageRepresentation::class => 'site_pages',
            \Omeka\Api\Representation\JobRepresentation::class => 'jobs',
            \Omeka\Api\Representation\ResourceReference::class => 'resources',
            \Omeka\Api\Representation\AssetRepresentation::class => 'assets',
            \Omeka\Api\Representation\ApiResourceRepresentation::class => 'api_resources',
            // Modules.
            \Annotate\Api\Representation\AnnotationRepresentation::class => 'annotations',
        ];
        return $mapping[$class] ?? null;
    }

    protected function mapRepresentationToResourceTypeText(AbstractRepresentation $representation): ?string
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
        return $mapping[$class] ?? null;
    }
}
