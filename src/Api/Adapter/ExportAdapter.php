<?php
namespace BulkExport\Api\Adapter;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Entity\Export;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ExportAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'exporter_id' => 'exporterId',
    ];

    public function getResourceName()
    {
        return 'bulk_exports';
    }

    public function getRepresentationClass()
    {
        return ExportRepresentation::class;
    }

    public function getEntityClass()
    {
        return Export::class;
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

        if (isset($query['exporter_id'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    $this->getEntityClass() . '.exporter',
                    $this->createNamedParameter($qb, $query['exporter_id'])
                )
            );
        }

        if (isset($query['job_id'])) {
            $qb->andWhere(
                $qb->expr()->eq(
                    $this->getEntityClass() . '.job',
                    $this->createNamedParameter($qb, $query['job_id'])
                )
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        foreach ($data as $key => $value) {
            $posColon = strpos($key, ':');
            $keyName = $posColon === false ? $key : substr($key, $posColon + 1);
            $method = 'set' . ucfirst(Inflector::camelize($keyName));
            if (!method_exists($entity, $method)) {
                continue;
            }
            $entity->$method($value);
        }
    }
}
