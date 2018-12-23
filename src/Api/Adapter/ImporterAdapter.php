<?php
namespace Import\Api\Adapter;

use Import\Api\Representation\ImporterRepresentation;
use Import\Entity\Importer;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Common\Inflector\Inflector;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ImporterAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'import_importers';
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
            $method = 'set'.ucfirst(Inflector::camelize($key));
            if(!method_exists($entity,$method)) continue;
            $entity->$method($value);
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['id'])) {
            $qb->andWhere($qb->expr()->eq(
                $this->getEntityClass() . '.id',
                $this->createNamedParameter($qb, $query['id']))
            );
        }
//
//        if (isset($query['resource_type'])) {
//            $qb->andWhere($qb->expr()->eq(
//                $this->getEntityClass() . '.resource_type',
//                $this->createNamedParameter($qb, $query['resource_type']))
//            );
//        }
    }
}
