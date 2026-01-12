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
     * Mapping of custom labels to source fields for merging.
     *
     * Structure: [label => [field1, field2, ...], ...]
     *
     * @var array
     */
    protected $fieldsLabelsMapping = [];

    /**
     * Reverse mapping from source field to target label.
     *
     * Structure: [field => label, ...]
     *
     * @var array
     */
    protected $fieldsToLabelMapping = [];

    /**
     * Mapping of output field name to its shaper identifier.
     *
     * Used when the same metadata appears multiple times with different shapers.
     * Structure: [outputFieldName => shaperIdentifier, ...]
     *
     * @var array
     */
    protected $fieldShapersMap = [];

    /**
     * Mapping of output field name to its original source field.
     *
     * Used when the same metadata appears multiple times with different shapers.
     * Structure: [outputFieldName => sourceField, ...]
     *
     * @var array
     */
    protected $fieldSourcesMap = [];

    /**
     * @var string
     */
    protected $labelFormatFields = 'name';

    /**
     * Column information for value_per_column mode.
     *
     * Structure: [fieldName => ['max_count' => int, 'columns' => [...]], ...]
     * For metadata mode: columns = [['lang' => x, 'type' => y, 'visibility' => z, 'count' => n], ...]
     *
     * @var array
     */
    protected $fieldColumnsInfo = [];

    /**
     * Expanded field names for value_per_column mode.
     *
     * @var array
     */
    protected $expandedFieldNames = [];

    /**
     * Mapping from expanded field name to original field name and metadata.
     *
     * Structure: [expandedName => ['field' => x, 'index' => n, 'lang' => y, 'type' => z, 'visibility' => v], ...]
     *
     * @var array
     */
    protected $expandedFieldsMap = [];

    /**
     * @var array
     */
    protected $propertySizes = [
        'properties_max_500' => 500,
        'properties_min_500' => 501,
        'properties_max_1000' => 1000,
        'properties_min_1000' => 1001,
        'properties_max_5000' => 5000,
        'properties_min_5000' => 5001,
        // Kept for bad upgrade.
        'properties_small' => 5000,
        'properties_large' => 5001,
    ];

    protected $propertySizesMinMax = [
        'max_size' => [
            'properties_max_500',
            'properties_max_1000',
            'properties_max_5000',
            'properties_small',
        ],
        'min_size' => [
            'properties_min_500',
            'properties_min_1000',
            'properties_min_5000',
            'properties_large',
        ],
    ];

    /**
     * Parse the format_fields_labels option.
     *
     * Format: "Label = field1 field2" on each line.
     * - The label (before `=`) is the column header.
     * - The fields (after `=`, space-separated) are merged into that column.
     *
     * @param array $formatFieldsLabels Lines from the config option.
     * @return self
     */
    protected function parseFormatFieldsLabels(?array $formatFieldsLabels): self
    {
        $this->fieldsLabelsMapping = [];
        $this->fieldsToLabelMapping = [];

        if (empty($formatFieldsLabels)) {
            return $this;
        }

        foreach ($formatFieldsLabels as $line) {
            $line = trim((string) $line);
            if (!$line || strpos($line, '=') === false) {
                continue;
            }

            [$label, $fields] = array_map('trim', explode('=', $line, 2));
            if ($label === '' || $fields === '') {
                continue;
            }

            // Split fields by spaces.
            $fieldList = preg_split('/\s+/', $fields, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($fieldList)) {
                continue;
            }

            $this->fieldsLabelsMapping[$label] = $fieldList;

            // Build reverse mapping.
            foreach ($fieldList as $field) {
                $this->fieldsToLabelMapping[$field] = $label;
            }
        }

        return $this;
    }

    /**
     * Reorder and filter field names according to format_fields_labels.
     *
     * Fields listed in format_fields_labels come first in their specified order.
     * Other fields are appended after.
     *
     * @param array $fieldNames Original field names.
     * @return array Reordered field names with merged fields replaced by labels.
     */
    protected function applyFieldsLabelsOrder(array $fieldNames): array
    {
        if (empty($this->fieldsLabelsMapping)) {
            return $fieldNames;
        }

        $result = [];
        $usedFields = [];

        // First, add fields from format_fields_labels in order.
        foreach ($this->fieldsLabelsMapping as $label => $fields) {
            // Check if any of the source fields exist in fieldNames.
            $hasField = false;
            foreach ($fields as $field) {
                if (in_array($field, $fieldNames)) {
                    $hasField = true;
                    $usedFields[] = $field;
                }
            }
            if ($hasField) {
                // Use the label as the output field name.
                $result[] = $label;
            }
        }

        // Then, add remaining fields that are not mapped.
        foreach ($fieldNames as $fieldName) {
            if (!in_array($fieldName, $usedFields)) {
                $result[] = $fieldName;
            }
        }

        return $result;
    }

    /**
     * Get the source fields for a given output field name.
     *
     * If the field is a merged label, returns all source fields.
     * If the field has a shaper suffix, returns the original source field.
     * Otherwise returns the field itself.
     *
     * @param string $fieldName Output field name (may be a label or shaper-suffixed).
     * @return array List of source field names.
     */
    protected function getSourceFieldsForOutput(string $fieldName): array
    {
        // Check if this is a merged field (format_fields_labels).
        if (isset($this->fieldsLabelsMapping[$fieldName])) {
            return $this->fieldsLabelsMapping[$fieldName];
        }
        // Check if this is a shaper-suffixed field (multiple shapers).
        if (isset($this->fieldSourcesMap[$fieldName])) {
            return [$this->fieldSourcesMap[$fieldName]];
        }
        return [$fieldName];
    }

    /**
     * Get the shaper identifier for a given output field name.
     *
     * @param string $fieldName Output field name.
     * @return string|null Shaper identifier or null if no specific shaper.
     */
    protected function getShaperForField(string $fieldName): ?string
    {
        // First check explicit mapping from multiple shapers feature.
        if (isset($this->fieldShapersMap[$fieldName])) {
            return $this->fieldShapersMap[$fieldName];
        }
        // Fall back to simple key-value lookup (backwards compatibility).
        return $this->options['metadata_shapers'][$fieldName] ?? null;
    }

    /**
     * Parse metadata_shapers and prepare mappings for multiple shapers per field.
     *
     * Format (DataTextarea): [['metadata' => 'x', 'shaper' => 'y'], ...]
     *
     * When the same metadata has multiple shapers, creates unique output field
     * names with shaper suffix (e.g., "dcterms:title [Uppercase]").
     *
     * @return self
     */
    protected function parseMetadataShapers(): self
    {
        $this->fieldShapersMap = [];
        $this->fieldSourcesMap = [];

        $metadataShapers = $this->options['metadata_shapers'] ?? [];
        if (empty($metadataShapers)) {
            return $this;
        }

        // Group by metadata field.
        $shapersByMetadata = [];
        foreach ($metadataShapers as $entry) {
            $metadata = trim((string) ($entry['metadata'] ?? ''));
            $shaper = trim((string) ($entry['shaper'] ?? ''));
            if ($metadata !== '' && $shaper !== '') {
                $shapersByMetadata[$metadata][] = $shaper;
            }
        }

        // Build mappings.
        $simpleMapping = [];
        foreach ($shapersByMetadata as $metadata => $shapers) {
            $shapers = array_unique($shapers);
            if (count($shapers) === 1) {
                // Single shaper: use simple key-value format.
                $simpleMapping[$metadata] = $shapers[0];
            } else {
                // Multiple shapers: create unique field names with shaper suffix.
                foreach ($shapers as $shaper) {
                    $uniqueFieldName = $metadata . ' [' . $shaper . ']';
                    $this->fieldShapersMap[$uniqueFieldName] = $shaper;
                    $this->fieldSourcesMap[$uniqueFieldName] = $metadata;
                    $simpleMapping[$uniqueFieldName] = $shaper;
                }
            }
        }

        // Replace options with normalized format.
        $this->options['metadata_shapers'] = $simpleMapping;

        return $this;
    }

    /**
     * Get additional fields to add for multiple shapers.
     *
     * When a metadata field has multiple shapers, we need to add extra
     * output columns for each additional shaper.
     *
     * @param array $fieldNames Current field names.
     * @return array Field names with additional shaper columns.
     */
    protected function addMultipleShaperFields(array $fieldNames): array
    {
        if (empty($this->fieldSourcesMap)) {
            return $fieldNames;
        }

        $result = [];
        $addedShaperFields = [];

        foreach ($fieldNames as $fieldName) {
            $result[] = $fieldName;

            // Check if this field has multiple shapers.
            foreach ($this->fieldSourcesMap as $uniqueFieldName => $sourceField) {
                if ($sourceField === $fieldName && !in_array($uniqueFieldName, $addedShaperFields)) {
                    $result[] = $uniqueFieldName;
                    $addedShaperFields[] = $uniqueFieldName;
                }
            }
        }

        return $result;
    }

    /**
     * @param array $listFieldNames Fields to prepare instead of default list.
     * @param array $listFieldsToExclude Fields to exclude
     * @return self
     */
    protected function prepareFieldNames(?array $listFieldNames = null, ?array $listFieldsToExclude = null): self
    {
        if (is_array($this->fieldNames)) {
            return $this;
        }

        $listFieldNames = $this->cleanListFieldNames($listFieldNames);
        $listFieldsToExclude = $this->cleanListFieldNames($listFieldsToExclude);

        $entityClasses = array_map([$this, 'mapResourceTypeToEntity'], $this->options['resource_types'] ?? []);
        $resourceIds = $this->resourceIds ?? [];
        $unlimitedUsedProperties = array_keys($this->getUsedPropertiesByTerm([
            'entity_classes' => $entityClasses,
            'resource_ids' => $resourceIds,
        ]));
        $this->options['resource_types'] = $this->options['resource_types'] ?: [];

        if ($listFieldNames) {
            $this->fieldNames = $listFieldNames;
            $this->fieldNames = $this->managePropertiesList($listFieldNames);
            $hasPropertiesMinMax = (bool) array_intersect(array_keys($this->propertySizes), $listFieldNames);
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
            // TODO Why is there a check on max size here?
            $hasPropertiesMinMax = true;
            $usedProperties = array_keys($this->getUsedPropertiesByTerm([
                'entity_classes' => $entityClasses,
                'resource_ids' => $resourceIds,
                'max_size' => 5000,
            ]));
            $this->fieldNames = array_merge($this->fieldNames, $usedProperties);
        }

        if ($hasProperties && in_array('oa:Annotation', $this->options['resource_types'])) {
            foreach (array_keys($this->getUsedPropertiesByTerm([
                'entity_classes' => [\Annotate\Entity\AnnotationBody::class],
                'resource_ids' => $resourceIds,
            ])) as $property) {
                $this->fieldNames[] = 'oa:hasBody/' . $property;
            }
            foreach (array_keys($this->getUsedPropertiesByTerm([
                'entity_classes' => [\Annotate\Entity\AnnotationTarget::class],
                'resource_ids' => $resourceIds,
            ])) as $property) {
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
                'Some properties are not exported because they contain more or less than 500, 1000 or 5000 characters: {properties}.', // @translate
                ['properties' => $missingProperties]
            );
        }

        // Parse metadata_shapers for multiple shapers per field support.
        $this->parseMetadataShapers();

        // Add additional columns for fields with multiple shapers.
        $this->fieldNames = $this->addMultipleShaperFields($this->fieldNames);

        // Apply format_fields_labels for custom ordering and merging.
        $formatFieldsLabels = $this->options['format_fields_labels'] ?? [];
        if ($formatFieldsLabels) {
            $this->parseFormatFieldsLabels($formatFieldsLabels);
            $this->fieldNames = $this->applyFieldsLabelsOrder($this->fieldNames);
        }

        $this->fieldNames = array_values($this->fieldNames);

        return $this;
    }

    protected function cleanListFieldNames(?array $listFieldNames): array
    {
        if (!$listFieldNames) {
            return [];
        }

        // Clean the list field names for min/max sizes.
        $hasPropertiesMinMax = (bool) array_intersect(array_keys($this->propertySizes), $listFieldNames);
        if ($hasPropertiesMinMax) {
            $maxSizes = array_intersect($this->propertySizesMinMax['max_size'], $listFieldNames);
            if (count($maxSizes) > 1) {
                // Keep the last minimum, that is the greatest.
                array_pop($maxSizes);
                $listFieldNames = array_diff($listFieldNames, $maxSizes);
            }
            $minSizes = array_intersect($this->propertySizesMinMax['min_size'], $listFieldNames);
            if (count($minSizes) > 1) {
                // Keep the first maximum, that is the smallest.
                array_shift($minSizes);
                $listFieldNames = array_diff($listFieldNames, $minSizes);
            }
        }

        return $listFieldNames;
    }

    protected function managePropertiesList(?array $listFieldNames): array
    {
        if (!$listFieldNames) {
            return [];
        }

        $entityClasses = array_map([$this, 'mapResourceTypeToEntity'], $this->options['resource_types']);
        $resourceIds = $this->resourceIds ?? [];

        $index = array_search('properties', $listFieldNames);
        if ($index !== false) {
            unset($listFieldNames[$index]);
            $usedProperties = array_keys($this->getUsedPropertiesByTerm([
                'entity_classes' => $entityClasses,
                'resource_ids' => $resourceIds,
            ]));
            $listFieldNames = array_merge($listFieldNames, $usedProperties);
        }

        $maxSizes = array_intersect($this->propertySizesMinMax['max_size'], $listFieldNames);
        if ($maxSizes) {
            $listFieldNames = array_diff($listFieldNames, $maxSizes);
            $maxSize = array_pop($maxSizes);
            $usedProperties = array_keys($this->getUsedPropertiesByTerm([
                'entity_classes' => $entityClasses,
                'resource_ids' => $resourceIds,
                'max_size' => $this->propertySizes[$maxSize],
            ]));
            $listFieldNames = array_merge($listFieldNames, $usedProperties);
        }

        $minSizes = array_intersect($this->propertySizesMinMax['min_size'], $listFieldNames);
        if ($minSizes) {
            $listFieldNames = array_diff($listFieldNames, $minSizes);
            $minSize = reset($minSizes);
            $usedProperties = array_keys($this->getUsedPropertiesByTerm([
                'entity_classes' => $entityClasses,
                'resource_ids' => $resourceIds,
                'min_size' => $this->propertySizes[$minSize],
            ]));
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
            // If this is a custom label from format_fields_labels, use it directly.
            if (isset($this->fieldsLabelsMapping[$fieldName])) {
                $this->fieldLabels[] = $fieldName;
            } else {
                $this->fieldLabels[] = $this->getFieldLabel($fieldName, $useFirstTemplateProperty);
            }
        }
        return $this;
    }

    protected function getFieldLabel($fieldName, bool $useFirstTemplateProperty = false): string
    {
        static $mapping;

        // Avoid to translate mapping each time.
        if ($mapping === null) {
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
                'o:media/o:media_type' => $this->translator->translate('File media type'), // @translate
                'o:media/o:size' => $this->translator->translate('File size'), // @translate,
                'o:media/o:original_url' => $this->translator->translate('Url to original'), // @translate
                'o:media/original_url' => $this->translator->translate('Original file url'), // @translate
                'o:media/o:thumbnails_url/large' => $this->translator->translate('Url to large thumbnail'), // @translate
                'o:media/o:thumbnails_url/medium' => $this->translator->translate('Url to medium thumbnail'), // @translate
                'o:media/o:thumbnails_url/square' => $this->translator->translate('Url to square thumbnail'), // @translate
                'o:media/o:filename' => 'File name of original', // @translate
                'o:media/o:filename/large' => 'File name of large thumbnail', // @translate
                'o:media/o:filename/medium' => 'File name of medium thumbnail', // @translate
                'o:media/o:filename/square' => 'File name of square thumbnail', // @translate
                'o:asset' => $this->translator->translate('Asset'), // @translate
                'o:asset/o:id' => $this->translator->translate('Asset id'), // @translate
                'o:asset/o:asset_url' => $this->translator->translate('Asset url'), // @translate
                'o:asset/o:filename' => $this->translator->translate('Asset file name'), // @translate
                '(o:asset/o:id | o:primary_media/o:id)[1]' => $this->translator->translate('Asset id if any, else primary media id'), // @translate
                '(o:asset/o:asset_url | o:primary_media/o:original_url)[1]' => $this->translator->translate('Asset url if any, else primary media original url'), // @translate
                '(o:asset/o:asset_url | o:primary_media/o:thumbnail_urls/large)[1]' => $this->translator->translate('Asset url if any, else primary media large url'), // @translate
                '(o:asset/o:asset_url | o:primary_media/o:thumbnail_urls/medium)[1]' => $this->translator->translate('Asset url if any, else primary media medium url'), // @translate
                '(o:asset/o:asset_url | o:primary_media/o:thumbnail_urls/square)[1]' => $this->translator->translate('Asset url if any, else primary media square url'), // @translate
                '(o:asset/o:filename | o:primary_media/o:filename)[1]' => $this->translator->translate('Asset file name if any, else primary media original file name'), // @translate
                '(o:asset/o:filename | o:primary_media/o:filename/large)[1]' => $this->translator->translate('Asset file name if any, else primary media large file name'), // @translate
                '(o:asset/o:filename | o:primary_media/o:filename/medium)[1]' => $this->translator->translate('Asset file name if any, else primary media medium file name'), // @translate
                '(o:asset/o:filename | o:primary_media/o:filename/square)[1]' => $this->translator->translate('Asset file name if any, else primary media square file name'), // @translate
                'o:annotation' => $this->translator->translate('Annotation'), // @translate
                'url' => $this->translator->translate('Url'), // @translate,
                'resource_type' => $this->translator->translate('Resource type'), // @translate,
                // Modules.
                'o-module-folksonomy:tag' => $this->translator->translate('Tag'), // @translate
            ];
        }

        if (isset($mapping[$fieldName])) {
            return $mapping[$fieldName];
        }

        if (strpos($fieldName, '[')) {
            $base = strtok($fieldName, '[');
            $property = trim(strtok('['), ' []');
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
            'o-module-folksonomy:tag' => \Folksonomy\Entity\Tag::class,
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
            'o:media/o:original_url',
            'o:media/original_url',
            'o:media/o:thumbnails_url/large',
            'o:media/o:thumbnails_url/medium',
            'o:media/o:thumbnails_url/square',
            'o:media/o:filename',
            'o:media/o:filename/large',
            'o:media/o:filename/medium',
            'o:media/o:filename/square',
            'o:annotation',
            */
            'o:asset',
            'o:asset/o:id',
            'o:asset/o:asset_url',
            'o:asset/o:filename',
            '(o:asset/o:id | o:primary_media/o:id)[1]',
            '(o:asset/o:asset_url | o:primary_media/o:original_url)[1]',
            '(o:asset/o:asset_url | o:primary_media/o:thumbnail_urls/large)[1]',
            '(o:asset/o:asset_url | o:primary_media/o:thumbnail_urls/medium)[1]',
            '(o:asset/o:asset_url | o:primary_media/o:thumbnail_urls/square)[1]',
            '(o:asset/o:filename | o:primary_media/o:filename)[1]',
            '(o:asset/o:filename | o:primary_media/o:filename/large)[1]',
            '(o:asset/o:filename | o:primary_media/o:filename/medium)[1]',
            '(o:asset/o:filename | o:primary_media/o:filename/square)[1]',
            'url',
            'resource_type',
            // Module History Log.
            'operation',
        ];
        return in_array($fieldName, $singles);
    }

    /**
     * Check if field is a property (has colon but not o: prefix).
     */
    protected function isPropertyField(string $fieldName): bool
    {
        return strpos($fieldName, ':') !== false
            && strpos($fieldName, 'o:') !== 0
            && strpos($fieldName, 'oa:') !== 0;
    }

    /**
     * Check if value_per_column mode is enabled.
     */
    protected function isValuePerColumnMode(): bool
    {
        return !empty($this->options['value_per_column']);
    }

    /**
     * Check if column_metadata mode is enabled (independent of value_per_column).
     */
    protected function hasColumnMetadataMode(): bool
    {
        return !empty($this->getColumnMetadataOptions());
    }

    /**
     * Check if any column expansion mode is active.
     *
     * Returns true if either value_per_column or column_metadata is enabled.
     */
    protected function hasColumnExpansionMode(): bool
    {
        return $this->isValuePerColumnMode() || $this->hasColumnMetadataMode();
    }

    /**
     * Get the column metadata options (language, datatype, visibility).
     *
     * @return array Array of enabled metadata keys.
     */
    protected function getColumnMetadataOptions(): array
    {
        $options = $this->options['column_metadata'] ?? [];
        if (!is_array($options)) {
            return [];
        }
        return array_filter($options);
    }

    /**
     * Pre-scan resources to calculate max value counts per field.
     *
     * This is called before export when value_per_column or column_metadata
     * mode is enabled. It iterates through all resources to find the maximum
     * number of values for each property field.
     *
     * When column_metadata options are set, it also tracks unique combinations
     * of language, datatype, and visibility.
     *
     * @param array $resourceIds Resource IDs grouped by type.
     * @return self
     */
    protected function prescanResourcesForColumns(array $resourceIds): self
    {
        if (!$this->hasColumnExpansionMode()) {
            return $this;
        }

        $this->fieldColumnsInfo = [];
        $columnMetadata = $this->getColumnMetadataOptions();
        $hasMetadataMode = !empty($columnMetadata);

        // Initialize field info for all property fields.
        foreach ($this->fieldNames as $fieldName) {
            if ($this->isPropertyField($fieldName) && !$this->isSingleField($fieldName)) {
                $this->fieldColumnsInfo[$fieldName] = [
                    'max_count' => 0,
                    'columns' => [],
                ];
            }
        }

        if (empty($this->fieldColumnsInfo)) {
            return $this;
        }

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');

        foreach ($resourceIds as $resourceType => $ids) {
            if (empty($ids)) {
                continue;
            }

            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            if (!$resourceName) {
                continue;
            }
            $adapter = $services->get('Omeka\ApiAdapterManager')->get($resourceName);

            // Process in batches to avoid memory issues.
            $batchSize = 100;
            $chunks = array_chunk($ids, $batchSize);

            foreach ($chunks as $chunk) {
                $resources = $this->api->search($resourceName, [
                    'id' => $chunk,
                ], ['finalize' => false])->getContent();

                foreach ($resources as $resource) {
                    $resource = $adapter->getRepresentation($resource);
                    $this->scanResourceForColumnInfo($resource, $hasMetadataMode, $columnMetadata);
                    unset($resource);
                }

                unset($resources);
                $entityManager->clear();
            }
        }

        return $this;
    }

    /**
     * Scan a single resource to update column info.
     *
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
     * @param bool $hasMetadataMode
     * @param array $columnMetadata
     */
    protected function scanResourceForColumnInfo($resource, bool $hasMetadataMode, array $columnMetadata): void
    {
        foreach ($this->fieldColumnsInfo as $fieldName => &$info) {
            $values = $resource->value($fieldName, ['all' => true]);
            $valueCount = count($values);

            if (!$hasMetadataMode) {
                // Simple mode: just track max count.
                if ($valueCount > $info['max_count']) {
                    $info['max_count'] = $valueCount;
                }
            } else {
                // Metadata mode: track by language/datatype/visibility combinations.
                $metadataCounts = [];
                foreach ($values as $value) {
                    $key = $this->getValueMetadataKey($value, $columnMetadata);
                    $metadataCounts[$key] = ($metadataCounts[$key] ?? 0) + 1;
                }

                // Update max counts per metadata combination.
                foreach ($metadataCounts as $key => $count) {
                    if (!isset($info['columns'][$key])) {
                        $info['columns'][$key] = [
                            'metadata' => $this->parseValueMetadataKey($key, $columnMetadata),
                            'max_count' => 0,
                        ];
                    }
                    if ($count > $info['columns'][$key]['max_count']) {
                        $info['columns'][$key]['max_count'] = $count;
                    }
                }

                // Also update total max count.
                if ($valueCount > $info['max_count']) {
                    $info['max_count'] = $valueCount;
                }
            }
        }
        unset($info);
    }

    /**
     * Get a key string representing value metadata for grouping.
     *
     * @param \Omeka\Api\Representation\ValueRepresentation $value
     * @param array $columnMetadata
     * @return string
     */
    protected function getValueMetadataKey($value, array $columnMetadata): string
    {
        $parts = [];
        if (in_array('language', $columnMetadata)) {
            $parts[] = 'lang:' . ($value->lang() ?? '');
        }
        if (in_array('datatype', $columnMetadata)) {
            $parts[] = 'type:' . $value->type();
        }
        if (in_array('visibility', $columnMetadata)) {
            $parts[] = 'vis:' . ($value->isPublic() ? '1' : '0');
        }
        return implode('|', $parts) ?: 'default';
    }

    /**
     * Parse a metadata key back into its components.
     *
     * @param string $key
     * @param array $columnMetadata
     * @return array
     */
    protected function parseValueMetadataKey(string $key, array $columnMetadata): array
    {
        $result = [
            'language' => null,
            'datatype' => null,
            'visibility' => null,
        ];

        if ($key === 'default') {
            return $result;
        }

        $parts = explode('|', $key);
        foreach ($parts as $part) {
            if (strpos($part, 'lang:') === 0) {
                $result['language'] = substr($part, 5);
            } elseif (strpos($part, 'type:') === 0) {
                $result['datatype'] = substr($part, 5);
            } elseif (strpos($part, 'vis:') === 0) {
                $result['visibility'] = substr($part, 4) === '1';
            }
        }

        return $result;
    }

    /**
     * Expand field names for column expansion modes.
     *
     * After pre-scan, this method expands each property field into multiple
     * columns based on the configuration:
     * - value_per_column only: repeat field name for each value
     * - column_metadata only: one column per metadata combination (values joined)
     * - both: columns per metadata combination with index for each value
     *
     * @return self
     */
    protected function expandFieldNamesForColumns(): self
    {
        if (!$this->hasColumnExpansionMode() || empty($this->fieldColumnsInfo)) {
            $this->expandedFieldNames = $this->fieldNames;
            return $this;
        }

        $this->expandedFieldNames = [];
        $this->expandedFieldsMap = [];
        $columnMetadata = $this->getColumnMetadataOptions();
        $hasMetadataMode = !empty($columnMetadata);
        $hasValuePerColumn = $this->isValuePerColumnMode();

        foreach ($this->fieldNames as $fieldName) {
            if (!isset($this->fieldColumnsInfo[$fieldName])) {
                // Not a property field or single field - keep as is.
                $this->expandedFieldNames[] = $fieldName;
                continue;
            }

            $info = $this->fieldColumnsInfo[$fieldName];

            if ($hasMetadataMode && !empty($info['columns'])) {
                // Metadata mode: create columns per metadata combination.
                foreach ($info['columns'] as $key => $columnInfo) {
                    $suffix = $this->buildColumnMetadataSuffix($columnInfo['metadata'], $columnMetadata);

                    if ($hasValuePerColumn) {
                        // Both modes: one column per value within each metadata group.
                        for ($i = 1; $i <= $columnInfo['max_count']; $i++) {
                            $expandedName = $columnInfo['max_count'] > 1
                                ? $fieldName . $suffix . ' [' . $i . ']'
                                : $fieldName . $suffix;
                            $this->expandedFieldNames[] = $expandedName;
                            $this->expandedFieldsMap[$expandedName] = [
                                'field' => $fieldName,
                                'index' => $i,
                                'metadata_key' => $key,
                                'joined' => false,
                            ] + $columnInfo['metadata'];
                        }
                    } else {
                        // Metadata only: one column per metadata group, values joined.
                        $expandedName = $fieldName . $suffix;
                        $this->expandedFieldNames[] = $expandedName;
                        $this->expandedFieldsMap[$expandedName] = [
                            'field' => $fieldName,
                            'index' => null,
                            'metadata_key' => $key,
                            'joined' => true,
                        ] + $columnInfo['metadata'];
                    }
                }
            } elseif ($hasValuePerColumn) {
                // value_per_column only: just repeat the field name.
                for ($i = 1; $i <= $info['max_count']; $i++) {
                    // Use the same header name for all columns (as requested).
                    $this->expandedFieldNames[] = $fieldName;
                    $this->expandedFieldsMap[$fieldName . '#' . $i] = [
                        'field' => $fieldName,
                        'index' => $i,
                        'metadata_key' => null,
                        'joined' => false,
                    ];
                }
            }
        }

        return $this;
    }

    /**
     * Build a suffix for column header based on metadata.
     *
     * @param array $metadata
     * @param array $columnMetadata
     * @return string
     */
    protected function buildColumnMetadataSuffix(array $metadata, array $columnMetadata): string
    {
        $parts = [];

        if (in_array('language', $columnMetadata) && $metadata['language'] !== null) {
            if ($metadata['language'] !== '') {
                $parts[] = '@' . $metadata['language'];
            }
        }
        if (in_array('datatype', $columnMetadata) && $metadata['datatype'] !== null) {
            $parts[] = '^^' . $metadata['datatype'];
        }
        if (in_array('visibility', $columnMetadata) && $metadata['visibility'] !== null) {
            if (!$metadata['visibility']) {
                $parts[] = '[private]';
            }
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
    }

    /**
     * Get resource values organized for column expansion output.
     *
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
     * @param string $fieldName Original field name.
     * @param string $separator Separator for joining values (used in metadata-only mode).
     * @return array Values organized by column position.
     */
    protected function getValuesForColumnOutput($resource, string $fieldName, string $separator = ' | '): array
    {
        if (!isset($this->fieldColumnsInfo[$fieldName])) {
            return [];
        }

        $info = $this->fieldColumnsInfo[$fieldName];
        $columnMetadata = $this->getColumnMetadataOptions();
        $hasMetadataMode = !empty($columnMetadata) && !empty($info['columns']);
        $hasValuePerColumn = $this->isValuePerColumnMode();

        $values = $resource->value($fieldName, ['all' => true]);

        if (!$hasMetadataMode) {
            // value_per_column only: return values in order, padded with empty strings.
            $result = [];
            for ($i = 0; $i < $info['max_count']; $i++) {
                $result[] = isset($values[$i]) ? (string) $values[$i] : '';
            }
            return $result;
        }

        // Metadata mode: organize values by metadata combination.
        $valuesByMetadata = [];
        foreach ($values as $value) {
            $key = $this->getValueMetadataKey($value, $columnMetadata);
            $valuesByMetadata[$key][] = (string) $value;
        }

        // Build result array in column order.
        $result = [];
        foreach ($info['columns'] as $key => $columnInfo) {
            $columnValues = $valuesByMetadata[$key] ?? [];

            if ($hasValuePerColumn) {
                // Both modes: one value per column, padded with empty strings.
                for ($i = 0; $i < $columnInfo['max_count']; $i++) {
                    $result[] = $columnValues[$i] ?? '';
                }
            } else {
                // Metadata only: join all values for this metadata group.
                $result[] = implode($separator, $columnValues);
            }
        }

        return $result;
    }

    /**
     * Get the expanded field names for output (headers).
     *
     * @return array
     */
    protected function getExpandedFieldNames(): array
    {
        return $this->expandedFieldNames ?: $this->fieldNames;
    }

    /**
     * Check if the expanded field names differ from original.
     *
     * @return bool
     */
    protected function hasExpandedFields(): bool
    {
        return !empty($this->expandedFieldNames) && $this->expandedFieldNames !== $this->fieldNames;
    }
}
