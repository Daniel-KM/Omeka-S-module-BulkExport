<?php
namespace BulkImport\Api\Adapter;

use BulkImport\Api\Representation\ImportRepresentation;
use BulkImport\Entity\Import;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ImportAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'bulk_imports';
    }

    public function getRepresentationClass()
    {
        return ImportRepresentation::class;
    }

    public function getEntityClass()
    {
        return Import::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    $this->getEntityClass() . '.id',
                    $this->createNamedParameter($qb, $query['id'])
                )
            );
        }

        if (isset($query['importer_id'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    $this->getEntityClass() . '.importer',
                    $this->createNamedParameter($qb, $query['importer_id'])
                )
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        foreach ($data as $key => $value) {
            $method = 'set' . ucfirst(Inflector::camelize($key));
            if (!method_exists($entity, $method)) {
                continue;
            }
            $entity->$method($value);
        }
    }
}
