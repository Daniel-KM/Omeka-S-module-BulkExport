<?php
namespace Import\Api\Adapter;


use Import\Api\Representation\ImportRepresentation;
use Import\Entity\Import;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Common\Inflector\Inflector;

class ImportAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'import_imports';
    }

    public function getRepresentationClass()
    {
        return ImportRepresentation::class;
    }

    public function getEntityClass()
    {
        return Import::class;
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
    }
}
