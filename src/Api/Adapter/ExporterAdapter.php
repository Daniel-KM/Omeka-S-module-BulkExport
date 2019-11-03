<?php
namespace BulkExport\Api\Adapter;

use BulkExport\Api\Representation\ExporterRepresentation;
use BulkExport\Entity\Exporter;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ExporterAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'label' => 'label',
        'writer_class' => 'writerClass',
    ];

    public function getResourceName()
    {
        return 'bulk_exporters';
    }

    public function getRepresentationClass()
    {
        return ExporterRepresentation::class;
    }

    public function getEntityClass()
    {
        return Exporter::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
        $expr = $qb->expr();

        if (isset($query['id'])) {
            $qb->andWhere(
                $expr->eq(
                    $alias . '.id',
                    $this->createNamedParameter($qb, $query['id'])
                )
            );
        }

        if (isset($query['owner_id']) && is_numeric($query['owner_id'])) {
            $userAlias = $this->createAlias();
            $qb->innerJoin(
                $alias . '.owner',
                $userAlias
            );
            $qb->andWhere(
                $qb->eq(
                    $userAlias . '.id',
                    $this->createNamedParameter($qb, $query['owner_id'])
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
