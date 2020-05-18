<?php
namespace BulkExport\Traits;

trait ListTermsTrait
{
    /**
     * @var array
     */
    protected $propertiesByTerm;

    /**
     * @var array
     */
    protected $resourceClassesByTerm;

    /**
     * @return array
     */
    protected function getPropertiesByTerm()
    {
        if ($this->propertiesByTerm) {
            return $this->propertiesByTerm;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $qb
            ->select([
                'DISTINCT property.id AS id',
                "CONCAT(vocabulary.prefix, ':', property.local_name) AS term",
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id',
                'property.id',
            ])
            ->from('property', 'property')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('property.id', 'asc')
            ->addGroupBy('property.id')
        ;
        $stmt = $connection->executeQuery($qb);
        // Fetch by key pair is not supported by doctrine 2.0.
        $terms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->propertiesByTerm = array_column($terms, 'id', 'term');
        return $this->propertiesByTerm;
    }

    /**
     * @return array
     */
    protected function getResourceClassesByTerm()
    {
        if ($this->resourceClassesByTerm) {
            return $this->resourceClassesByTerm;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $qb
            ->select([
                'DISTINCT resource_class.id AS id',
                "CONCAT(vocabulary.prefix, ':', resource_class.local_name) AS term",
                // Only the two first selects are needed, but some databases
                // require "order by" or "group by" value to be in the select.
                'vocabulary.id',
                'resource_class.id',
            ])
            ->from('resource_class', 'resource_class')
            ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
            ->orderBy('vocabulary.id', 'asc')
            ->addOrderBy('resource_class.id', 'asc')
            ->addGroupBy('resource_class.id')
        ;
        $stmt = $connection->executeQuery($qb);
        // Fetch by key pair is not supported by doctrine 2.0.
        $terms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->resourceClassesByTerm = array_column($terms, 'id', 'term');
        return $this->resourceClassesByTerm;
    }

    protected function getUsedPropertiesByTerm(array $resourceClasses = null)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // List only properties that are used.
        // TODO Limit with the query (via adapter).
        $qb = $connection->createQueryBuilder();
        $qb
            ->select([
                'DISTINCT(CONCAT(vocabulary.prefix, ":", property.local_name)) AS term',
                // Only the first select is needed, but some databases require
                // "order by" value to be in select.
                'vocabulary.id',
                'property.id',
            ])
            ->from('value', 'value')
            ->innerJoin('value', 'property', 'property', 'property.id = value.property_id')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'vocabulary.id = property.vocabulary_id')
            // Order by vocabulary and by property id, because Omeka orders them
            // with Dublin Core first.
            ->orderBy('vocabulary.id')
            ->addOrderBy('property.id')
        ;

        if ($resourceClasses) {
            $qb
            ->innerJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            ->andWhere($qb->expr()->in(
                'resource.resource_type',
                array_map([$connection, 'quote'], $resourceClasses)
            ));
        }

        $stmt = $connection->executeQuery($qb, $qb->getParameters());
        $terms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($terms, 'id', 'term');
    }
}
