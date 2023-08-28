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
        'owner_id' => 'ownerId',
        'job_id' => 'job',
        'comment' => 'comment',
        'filename' => 'filename',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'exporter' => 'exporter',
        'owner' => 'owner',
        'job' => 'job',
        'comment' => 'comment',
        'writer_params' => 'writerParams',
        'filename' => 'filename',
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

        if (isset($query['owner_id']) && is_numeric($query['owner_id'])) {
            $userAlias = $this->createAlias();
            $qb
                ->innerJoin('omeka_root.owner', $userAlias)
                ->andWhere($expr->eq(
                    'omeka_root.owner',
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
            if (!method_exists($entity, $method)) {
                continue;
            }
            $entity->$method($value);
        }
    }

    public function deleteEntity(Request $request)
    {
        /** @var \BulkExport\Entity\Export $entity */
        $entity = parent::deleteEntity($request);
        // Deletion rights is already checked.
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $filepath = $basePath . '/bulk_export/' . $entity->getFilename();
        if (file_exists($filepath) && is_writeable($filepath)) {
            unlink($filepath);
        }
        return $entity;
    }
}
