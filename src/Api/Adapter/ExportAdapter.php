<?php declare(strict_types=1);

namespace BulkExport\Api\Adapter;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Entity\Export;
use Doctrine\Inflector\InflectorFactory;
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

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['exporter_id'])) {
            $qb->andWhere(
                $expr->eq(
                    'omeka_root.exporter',
                    $this->createNamedParameter($qb, $query['exporter_id'])
                )
            );
        }

        if (isset($query['job_id'])) {
            $qb->andWhere(
                $expr->eq(
                    'omeka_root.job',
                    $this->createNamedParameter($qb, $query['job_id'])
                )
            );
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        $data = $request->getContent();
        $inflector = InflectorFactory::create()->build();
        foreach ($data as $key => $value) {
            $posColon = strpos($key, ':');
            $keyName = $posColon === false ? $key : substr($key, $posColon + 1);
            $method = 'set' . ucfirst($inflector->camelize($keyName));
            if (!method_exists($entity, $method)) {
                continue;
            }
            $entity->$method($value);
        }
    }
}
