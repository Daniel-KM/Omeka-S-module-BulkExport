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
     * @param array $listFieldNames Fields to prepare instead of default list.
     * @param array $listFieldsToExclude Fields to exclude
     * @return self
     */
    protected function prepareFieldNames(array $listFieldNames = null, array $listFieldsToExclude = null)
    {
        if (is_array($this->fieldNames)) {
            return $this;
        }

        $classes = array_map([$this, 'mapResourceTypeToClass'], $this->options['resource_types']);
        if ($listFieldNames) {
            $this->fieldNames = $listFieldNames;
            $index = array_search('properties', $listFieldNames);
            $hasProperties = $index !== false;
            if ($hasProperties) {
                unset($listFieldNames[$index]);
                $this->fieldNames = array_merge($listFieldNames, array_keys($this->getUsedPropertiesByTerm($classes)));
            }
        } else {
            $hasProperties = true;
            if (!empty($this->options['is_admin_request'])) {
                $this->fieldNames = [
                    'o:id',
                    'o:resource_template',
                    'o:resource_class',
                    'o:owner',
                    'o:is_public',
                ];
            } else {
                $this->fieldNames = [
                    'url',
                    'o:resource_class',
                ];
            }
            if (count($this->options['resource_types']) === 1) {
                switch (reset($this->options['resource_types'])) {
                    case 'o:ItemSet':
                        $this->fieldNames[] = 'o:is_open';
                        break;
                    case 'o:Item':
                        $this->fieldNames[] = 'o:item_set[o:id]';
                        $this->fieldNames[] = 'o:item_set[dcterms:title]';
                        $this->fieldNames[] = 'o:media[o:id]';
                        $this->fieldNames[] = 'o:media[file]';
                        break;
                    case 'o:Media':
                        $this->fieldNames[] = 'o:item[o:id]';
                        $this->fieldNames[] = 'o:item[dcterms:identifier]';
                        $this->fieldNames[] = 'o:item[dcterms:title]';
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
            $this->fieldNames = array_merge($this->fieldNames, array_keys($this->getUsedPropertiesByTerm($classes)));
        }

        if ($hasProperties && in_array('oa:Annotation', $this->options['resource_types'])) {
            foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationBody::class])) as $property) {
                $this->fieldNames[] = 'oa:hasBody[' . $property . ']';
            }
            foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationTarget::class])) as $property) {
                $this->fieldNames[] = 'oa:hasTarget[' . $property . ']';
            }
        }

        if (count($this->options['resource_types']) > 1 && !in_array('resource_type', $this->fieldNames)) {
            array_unshift($this->fieldNames, 'resource_type');
        }

        if ($listFieldsToExclude) {
            $this->fieldNames = array_diff($this->fieldNames, $listFieldsToExclude);
        }

        $this->fieldNames = array_values($this->fieldNames);

        return $this;
    }

    protected function prepareFieldLabels()
    {
        if (is_array($this->fieldLabels)) {
            return $this;
        }
        $this->fieldLabels = [];
        foreach ($this->fieldNames as $fieldName) {
            $this->fieldLabels[] = $this->getFieldLabel($fieldName);
        }
        return $this;
    }

    protected function getFieldLabel($fieldName)
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
                'o:resource[o:id]' => $this->translator->translate('Resource id'), // @translate
                'o:resource[dcterms:identifier]' => $this->translator->translate('Resource identifier'), // @translate
                'o:resource[dcterms:title]' => $this->translator->translate('Resource title'), // @translate
                'o:item_set' => $this->translator->translate('Item set'), // @translate
                'o:item_set[o:id]' => $this->translator->translate('Item set id'), // @translate
                'o:item_set[dcterms:title]' => $this->translator->translate('Item set'), // @translate
                'o:item' => $this->translator->translate('Item'), // @translate
                'o:item[o:id]' => $this->translator->translate('Item id'), // @translate
                'o:item[dcterms:identifier]' => $this->translator->translate('Item identifier'), // @translate
                'o:item[dcterms:title]' => $this->translator->translate('Item title'), // @translate
                'o:media' => $this->translator->translate('Media'), // @translate
                'o:media[o:id]' => $this->translator->translate('Media id'), // @translate
                'o:media[file]' => $this->translator->translate('Media file'), // @translate
                'o:media[media_type]' => $this->translator->translate('File media type'), // @translate,
                'o:media[size]' => $this->translator->translate('File size'), // @translate,
                'o:media[original_url]' => $this->translator->translate('Original file url'), // @translate,
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
            $second = isset($mapping[$property])
                ? $mapping[$property]
                : $this->translateProperty($property);
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
                    $first = isset($mapping[$base])
                        ? $mapping[$base]
                        : $base;
                    return sprintf(
                        '%1$s: %2$s', // @translate
                        $first,
                        $second
                    );
            }
        }

        return $this->translateProperty($fieldName);
    }

    /**
     * @todo Factorize with \BulkExport\Writer\AbstractWriter::mapResourceTypeToClass()
     * @param string $jsonResourceType Or api resource type.
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
}
