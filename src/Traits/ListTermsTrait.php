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
}
