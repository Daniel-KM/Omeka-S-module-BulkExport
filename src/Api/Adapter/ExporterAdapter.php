<?php declare(strict_types=1);

namespace BulkExport\Api\Adapter;

use BulkExport\Api\Representation\ExporterRepresentation;
use BulkExport\Entity\Exporter;
use Doctrine\Inflector\InflectorFactory;
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
        'owner_id' => 'owner',
        'formatter' => 'formatter',
        'writer' => 'writer', // @deprecated
    ];

    protected $scalarFields = [
        'id' => 'id',
        'label' => 'label',
        'owner' => 'owner',
        'formatter' => 'formatter',
        'writer' => 'writer', // @deprecated
        'config' => 'config',
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

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['owner_id']) && is_numeric($query['owner_id'])) {
            $userAlias = $this->createAlias();
            $qb
                ->innerJoin(
                    'omeka_root.owner',
                    $userAlias
                )
                ->andWhere($qb->eq(
                    'omeka_root.id',
                    $this->createNamedParameter($qb, $query['owner_id'])
                ));
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
            if (method_exists($entity, $method)) {
                $entity->$method($value);
            }
        }
    }
}
