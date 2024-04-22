<?php declare(strict_types=1);

namespace BulkExport\Traits;

trait ListTermsTrait
{
    /**
     * @var array
     */
    protected $propertyTemplateLabelsByTerm;

    /**
     * To be prepared ouside.
     *
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    /**
     * @todo Replace by an option to getPropertiesByTerm.
     *
     * @param array $options Associative array:
     * - entity_classes (array): limit properties to entity classes.
     * - min_size (int): property is removed if one value is smaller than it.
     * - max_size (int): property is removed if one value is larger than it.
     * @return array
     */
    protected function getUsedPropertiesByTerm(array $options = []): array
    {
        $options += [
            'entity_classes' => [],
            'min_size' => 0,
            'max_size' => 0,
        ];

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');

        // List only properties that are used.
        // TODO Limit with the query (via adapter).
        $qb = $connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select(
                'DISTINCT(CONCAT(vocabulary.prefix, ":", property.local_name)) AS term',
                'property.id AS id',
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id'
            )
            ->from('value', 'value')
            ->innerJoin('value', 'property', 'property', 'property.id = value.property_id')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'vocabulary.id = property.vocabulary_id')
            // Order by vocabulary and by property id, because Omeka orders them
            // with Dublin Core first.
            ->orderBy('vocabulary.id')
            ->addOrderBy('property.id')
        ;

        $bind = [];
        $types = [];
        if (count($options['entity_classes']) && !in_array(\Omeka\Entity\Resource::class, $options['entity_classes'])) {
            $qb
                ->innerJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
                ->andWhere($expr->in('resource.resource_type', ':entity_classes'))
            ;
            $bind['entity_classes'] = array_unique($options['entity_classes']);
            $types['entity_classes'] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        if ((int) $options['min_size'] > 0) {
            $qb
                ->andWhere('CHAR_LENGTH(`value`.`value`) >= ' . (int) $options['min_size']);
        }

        if ((int) $options['max_size'] > 0) {
            $qb
                ->andWhere($expr->orX(
                    '`value`.`value` IS NULL',
                    'CHAR_LENGTH(`value`.`value`) <= ' . (int) $options['max_size']
                ));
        }

        $terms = $connection->executeQuery($qb, $bind, $types)->fetchAllKeyValue();
        return array_map('intval', $terms);
    }

    /**
     * Only properties with a template label are returned.
     *
     * Warning: the same property cannot have multiple alternative labels.
     *
     * @todo Integrate getPropertyTemplateLabelsByTerm in Common ? Or too much specific?
     */
    protected function getPropertyTemplateLabelsByTerm(): array
    {
        if ($this->propertyTemplateLabelsByTerm) {
            return $this->propertyTemplateLabelsByTerm;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                "DISTINCT CONCAT(vocabulary.prefix, ':', property.local_name) AS term",
                'resource_template_property.alternate_label as label'
            )
            ->from('resource_template_property', 'resource_template_property')
            ->innerJoin('resource_template_property', 'property', 'property', 'property.id = resource_template_property.property_id')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'vocabulary.id = property.vocabulary_id')
            ->where('alternate_label IS NOT NULL')
            ->where('alternate_label != ""')
            ->addOrderBy('property.id', 'asc')
        ;
        $this->propertyTemplateLabelsByTerm = $connection->executeQuery($qb)->fetchAllKeyValue();
        return $this->propertyTemplateLabelsByTerm;
    }

    protected function translateProperty($property): string
    {
        $label = $this->easyMeta->propertyLabel($property);
        return $label
            ? ucfirst($this->translator->translate($label))
            : $property;
    }

    protected function translateResourceClass($resourceClass): string
    {
        $label = $this->easyMeta->resourceClassLabel($resourceClass);
        return $label
            ? ucfirst($this->translator->translate($label))
            : $resourceClass;
    }

    protected function translatePropertyTemplate($property): string
    {
        $labels = $this->getPropertyTemplateLabelsByTerm();
        return isset($labels[$property])
            ? ucfirst($labels[$property])
            : $this->translateProperty($property);
    }
}
