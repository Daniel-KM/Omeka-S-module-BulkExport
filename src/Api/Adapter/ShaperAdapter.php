<?php declare(strict_types=1);

namespace BulkExport\Api\Adapter;

use Common\Api\Adapter\CommonAdapterTrait;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ShaperAdapter extends AbstractEntityAdapter
{
    use CommonAdapterTrait;

    protected $sortFields = [
        'id' => 'id',
        'label' => 'label',
        'owner_id' => 'owner',
        // No sort by config.
        // 'config' => 'config',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'label' => 'label',
        'owner' => 'owner',
        'config' => 'config',
        'created' => 'created',
        'modified' => 'modified',
    ];

    protected $queryFields = [
        'id' => [
            'owner_id' => 'owner',
        ],
        'string' => [
            'label' => 'label',
        ],
        'datetime_operator' => [
            'created' => 'created',
            'modified' => 'modified',
        ],
    ];

    public function getResourceName()
    {
        return 'bulk_shapers';
    }

    public function getRepresentationClass()
    {
        return \BulkExport\Api\Representation\ShaperRepresentation::class;
    }

    public function getEntityClass()
    {
        return \BulkExport\Entity\Shaper::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $this->buildQueryFields($qb, $query);
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        $this->hydrateAuto($request, $entity, $errorStore);
        $this->updateTimestamps($request, $entity);
    }
}
