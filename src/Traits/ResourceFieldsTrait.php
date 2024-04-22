<?php declare(strict_types=1);

namespace BulkExport\Traits;

/**
 * The  trait ListTermsTrait should be used to get terms and labels.
 */
trait ResourceFieldsTrait
{
    /**
     * @var array
     */
    protected $fieldNames;

    /**
     * @var array
     */
    protected $fieldLabels;

    /**
     * @var string
     */
    protected $labelFormatFields = 'name';

    /**
     * @param array $listFieldNames Fields to prepare instead of default list.
     * @param array $listFieldsToExclude Fields to exclude
     * @return self
     */
    protected function prepareFieldNames(array $listFieldNames = null, array $listFieldsToExclude = null): self
    {
        if (is_array($this->fieldNames)) {
            return $this;
        }

        $entityClasses = array_map([$this, 'mapResourceTypeToEntity'], $this->options['resource_types']);
        $unlimitedUsedProperties = array_keys($this->getUsedPropertiesByTerm(['entity_classes' => $entityClasses]));
        $this->options['resource_types'] = $this->options['resource_types'] ?: [];

        if ($listFieldNames) {
            $this->fieldNames = $listFieldNames;
            $this->fieldNames = $this->managePropertiesList($listFieldNames);
            $hasPropertiesMinMax = in_array('properties_max_1000', $listFieldNames)
                || in_array('properties_min_1000', $listFieldNames)
                || in_array('properties_max_5000', $listFieldNames)
                || in_array('properties_min_5000', $listFieldNames)
                // Kept for bad upgrade.
                || in_array('properties_small', $listFieldNames)
                || in_array('properties_large', $listFieldNames);
            $hasProperties = in_array('properties', $listFieldNames) || $hasPropertiesMinMax;
            $usedProperties = array_diff($this->fieldNames, $listFieldNames);
        } else {
            $hasProperties = true;
            // Admin or api request.
            if (empty($this->options['is_site_request'])) {
                $this->fieldNames = [
                    'o:id',
                    'url',
                    'o:resource_template',
                    'o:resource_class',
                    'o:owner',
                    'o:is_public',
                ];
            } else {
                $this->fieldNames = [
                    'o:id',
                    'url',
                    'o:resource_template',
                    'o:resource_class',
                ];
            }
            if (count($this->options['resource_types']) === 1) {
                switch (reset($this->options['resource_types'])) {
                    case 'o:ItemSet':
                        $this->fieldNames[] = 'o:is_open';
                        break;
                    case 'o:Item':
                        $this->fieldNames[] = 'o:item_set/o:id';
                        $this->fieldNames[] = 'o:item_set/dcterms:title';
                        $this->fieldNames[] = 'o:media/o:id';
                        $this->fieldNames[] = 'o:media/file';
                        break;
                    case 'o:Media':
                        $this->fieldNames[] = 'o:item/o:id';
                        $this->fieldNames[] = 'o:item/dcterms:identifier';
                        $this->fieldNames[] = 'o:item/dcterms:title';
                        break;
                    case 'oa:Annotation':
                        $this->fieldNames[] = 'o:resource[o:id]';
                        $this->fieldNames[] = 'o:resource[dcterms:identifier]';
                        $this->fieldNames[] = 'o:resource[dcterms:title]';
                        break;
                    default:
                        break;
                }
            }
            $hasPropertiesMinMax = true;
            $usedProperties = array_keys($this->getUsedPropertiesByTerm(['entity_classes' => $entityClasses, 'max_size' => 5000]));
            $this->fieldNames = array_merge($this->fieldNames, $usedProperties);
        }

        if ($hasProperties && in_array('oa:Annotation', $this->options['resource_types'])) {
            foreach (array_keys($this->getUsedPropertiesByTerm(['entity_classes' => [\Annotate\Entity\AnnotationBody::class]])) as $property) {
                $this->fieldNames[] = 'oa:hasBody/' . $property;
            }
            foreach (array_keys($this->getUsedPropertiesByTerm(['entity_classes' => [\Annotate\Entity\AnnotationTarget::class]])) as $property) {
                $this->fieldNames[] = 'oa:hasTarget/' . $property;
            }
        }

        if (count($this->options['resource_types']) > 1 && !in_array('resource_type', $this->fieldNames)) {
            array_unshift($this->fieldNames, 'resource_type');
        }

        if ($listFieldsToExclude) {
            $toExclude = $this->managePropertiesList($listFieldsToExclude);
            $this->fieldNames = array_diff($this->fieldNames, $toExclude);
            $unlimitedUsedProperties = array_diff($unlimitedUsedProperties, $toExclude);
        }

        $missingProperties = array_diff($unlimitedUsedProperties, $usedProperties);
        if ($hasPropertiesMinMax && count($missingProperties)) {
            $this->logger->warn(
                'Some properties are not exported because they contain more or less than 1000 or 5000 characters: {properties}.', // @translate
                ['properties' => $missingProperties]
            );
        }

        $this->fieldNames = array_values($this->fieldNames);

        return $this;
    }

    protected function managePropertiesList(array $listFieldNames): array
    {
        $entityClasses = array_map([$this, 'mapResourceTypeToEntity'], $this->options['resource_types']);
        $index = array_search('properties', $listFieldNames);
        if ($index !== false) {
            unset($listFieldNames[$index]);
            $usedProperties = array_keys($this->getUsedPropertiesByTerm(['entity_classes' => $entityClasses]));
            $listFieldNames = array_merge($listFieldNames, $usedProperties);
        }
        $index1000 = array_search('properties_max_1000', $listFieldNames);
        $index5000 = array_search('properties_max_5000', $listFieldNames);
        $indexSmall = array_search('properties_small', $listFieldNames);
        if ($index1000 !== false || $index5000 !== false || $indexSmall !== false) {
            unset($listFieldNames[$index1000]);
            unset($listFieldNames[$index5000]);
            unset($listFieldNames[$indexSmall]);
            $usedProperties = array_keys($this->getUsedPropertiesByTerm(['entity_classes' => $entityClasses, 'max_size' => ($index5000 !== false || $indexSmall !== false) ? 5000 : 1000]));
            $listFieldNames = array_merge($listFieldNames, $usedProperties);
        }
        $index1000 = array_search('properties_min_1000', $listFieldNames);
        $index5000 = array_search('properties_min_5000', $listFieldNames);
        $indexLarge = array_search('properties_large', $listFieldNames);
        if ($index1000 !== false || $index5000 !== false) {
            unset($listFieldNames[$index1000]);
            unset($listFieldNames[$index5000]);
            $usedProperties = array_keys($this->getUsedPropertiesByTerm(['entity_classes' => $entityClasses, 'min_size' => $index1000 !== false ? 1001 : 5001]));
            $listFieldNames = array_merge($listFieldNames, $usedProperties);
        }
        return $listFieldNames;
    }

    protected function prepareFieldLabels(bool $useFirstTemplateProperty = false): self
    {
        if (is_array($this->fieldLabels)) {
            return $this;
        }
        $this->fieldLabels = [];
        foreach ($this->fieldNames as $fieldName) {
            $this->fieldLabels[] = $this->getFieldLabel($fieldName, $useFirstTemplateProperty);
        }
        return $this;
    }

    protected function getFieldLabel($fieldName, bool $useFirstTemplateProperty = false): string
    {
        static $mapping;

        // Avoid to translate mapping each time.
        if (is_null($mapping)) {
            $mapping = [
                'o:id' => $this->translator->translate('id'), // @translate,
                'o:resource_template' => $this->translator->translate('Resource template'), // @translate
                'o:resource_class' => $this->translator->translate('Resource class'), // @translate
                'o:owner' => $this->translator->translate('Owner'), // @translate
                'o:is_public' => $this->translator->translate('Is public'), // @translate
                'o:is_open' => $this->translator->translate('Is open'), // @translate
                'o:resource' => $this->translator->translate('Resource'), // @translate
                'o:resource/o:id' => $this->translator->translate('Resource id'), // @translate
                'o:resource/dcterms:identifier' => $this->translator->translate('Resource identifier'), // @translate
                'o:resource/dcterms:title' => $this->translator->translate('Resource title'), // @translate
                'o:item_set' => $this->translator->translate('Item set'), // @translate
                'o:item_set/o:id' => $this->translator->translate('Item set id'), // @translate
                'o:item_set/dcterms:title' => $this->translator->translate('Item set'), // @translate
                'o:item' => $this->translator->translate('Item'), // @translate
                'o:item/o:id' => $this->translator->translate('Item id'), // @translate
                'o:item/dcterms:identifier' => $this->translator->translate('Item identifier'), // @translate
                'o:item/dcterms:title' => $this->translator->translate('Item title'), // @translate
                'o:media' => $this->translator->translate('Media'), // @translate
                'o:media/o:id' => $this->translator->translate('Media id'), // @translate
                'o:media/file' => $this->translator->translate('Media file'), // @translate
                'o:media/o:source' => $this->translator->translate('Media source'), // @translate
                'o:media/o:media_type' => $this->translator->translate('File media type'), // @translate,
                'o:media/o:size' => $this->translator->translate('File size'), // @translate,
                'o:media/original_url' => $this->translator->translate('Original file url'), // @translate,
                'o:asset' => $this->translator->translate('Asset'), // @translate
                'o:annotation' => $this->translator->translate('Annotation'), // @translate
                'url' => $this->translator->translate('Url'), // @translate,
                'resource_type' => $this->translator->translate('Resource type'), // @translate,
            ];
        }

        if (isset($mapping[$fieldName])) {
            return $mapping[$fieldName];
        }

        if (strpos($fieldName, '[')) {
            $base = strtok($fieldName, '[');
            $property = trim(strok('['), ' []');
            $second = $mapping[$property]
                ?? ($useFirstTemplateProperty ? $this->translatePropertyTemplate($fieldName) : $this->translateProperty($fieldName));
            switch ($base) {
                case 'oa:hasBody':
                    return sprintf(
                        $this->translator->translate('Annotation body: %s'),  // @translate;
                        $second
                    );
                case 'oa:hasTarget':
                    return sprintf(
                        $this->translator->translate('Annotation target: %s'),  // @translate;
                        $second
                    );
                default:
                    $first = $mapping[$base] ?? $base;
                    return sprintf(
                        '%1$s: %2$s', // @translate
                        $first,
                        $second
                    );
            }
        }

        return $useFirstTemplateProperty ? $this->translatePropertyTemplate($fieldName) : $this->translateProperty($fieldName);
    }

    /**
     * @todo Factorize with \BulkExport\Writer\AbstractWriter::mapResourceTypeToEntity()
     * @param string $jsonResourceType Or api resource type.
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

    protected function isSingleField($fieldName): bool
    {
        // TODO The single fields may vary according to resource type.
        $singles = [
            'o:id',
            'o:resource_template',
            'o:resource_class',
            'o:owner',
            'o:is_public',
            'o:is_open',
            /*
            'o:resource',
            'o:resource/o:id',
            'o:resource/dcterms:identifier',
            'o:resource/dcterms:title',
            'o:item_set',
            'o:item_set/o:id',
            'o:item_set/dcterms:title',
            'o:item',
            'o:item/o:id',
            'o:item/dcterms:identifier',
            'o:item/dcterms:title',
            'o:media',
            'o:media/o:id',
            'o:media/file',
            'o:media/o:source',
            'o:media/o:media_type',
            'o:media/o:size',
            'o:media/original_url',
            'o:annotation',
            */
            'o:asset',
            'url',
            'resource_type',
            // Module History Log.
            'operation',
        ];
        return in_array($fieldName, $singles);
    }
}
