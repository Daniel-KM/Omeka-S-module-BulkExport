<?php
namespace BulkImport\Api\Adapter;

use BulkImport\Api\Representation\ImporterRepresentation;
use BulkImport\Entity\Importer;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ImporterAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'bulk_importers';
    }

    public function getRepresentationClass()
    {
        return ImporterRepresentation::class;
    }

    public function getEntityClass()
    {
        return Importer::class;
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

        // if (isset($query['resource_type'])) {
        //    $qb->andWhere($qb->expr()->eq(
        //        $this->getEntityClass() . '.resource_type',
        //        $this->createNamedParameter($qb, $query['resource_type']))
        //    );
        // }
    }
}
