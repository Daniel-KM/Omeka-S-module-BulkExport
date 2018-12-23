<?php

/*
 * Copyright 2017 Daniel Berthereau
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software. You can use, modify and/or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software’s author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user’s attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software’s suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace BulkImport\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Omeka\Api\Manager as ApiManager;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class FindResourcesFromIdentifiers extends AbstractPlugin
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @param Connection $connection
     * @param ApiManager $apiManager
     */
    public function __construct(Connection $connection, ApiManager $apiManager)
    {
        $this->connection = $connection;
        $this->api = $apiManager;
    }

    /**
     * Find a list of resource ids from a list of identifiers.
     *
     * When there are true duplicates and case insensitive duplicates, the first
     * case sensitive is returned, else the first case insensitive resource.
     *
     * @todo Manage Media source html.
     *
     * @param array|string $identifiers Identifiers should be unique. If a
     * string is sent, the result will be the resource.
     * @param string|int|array $identifierName Property as integer or term,
     * media ingester or "internal_id", or an array with multiple conditions.
     * @param string $resourceType The resource type if any.
     * @return array|int|null Associative array with the identifiers as key and the ids
     * or null as value. Order is kept, but duplicate identifiers are removed.
     * If $identifiers is a string, return directly the resource id, or null.
     */
    public function __invoke($identifiers, $identifierName, $resourceType = null)
    {
        $isSingle = is_string($identifiers);
        if ($isSingle) {
            $identifiers = [$identifiers];
        }
        $identifiers = array_unique(array_filter(array_map(function ($v) {
            return trim($v, "\t\n\r   ");
        }, $identifiers)));
        if (empty($identifiers)) {
            return $isSingle ? null : [];
        }

        $identifierType = null;
        // Process identifierName as an array.
        if (is_array($identifierName)) {
            if (isset($identifierName['o:ingester'])) {
                // TODO Currently, the media source cannot be html.
                if ($identifierName['o:ingester'] === 'html') {
                    return $isSingle ? null : [];
                }
                $identifierType = 'media_source';
                $resourceType = 'media';
                $itemId = empty($identifierName['o:item']['o:id']) ? null : $identifierName['o:item']['o:id'];
                $identifierName = $identifierName['o:ingester'];
            }
        }
        // Here, identifierName is a string or an integer.
        elseif (in_array($identifierName, ['internal_id'])) {
            $identifierType = 'internal_id';
        } elseif (is_numeric($identifierName)) {
            $identifierType = 'property';
            $identifierName = (int) $identifierName;
        } else {
            $result = $this->api
                ->search('properties', ['term' => $identifierName])->getContent();
            $identifierType = 'property';
            $identifierName = $result ? $result[0]->id() : null;
        }
        if (empty($identifierName)) {
            return $isSingle ? null : [];
        }

        if (!empty($resourceType)) {
            $resourceTypes = [
                'item_sets' => \Omeka\Entity\ItemSet::class,
                'items' => \Omeka\Entity\Item::class,
                'media' => \Omeka\Entity\Media::class,
                'resources' => '',
                // Avoid a check and make the plugin more flexible.
                'Omeka\Entity\ItemSet' => \Omeka\Entity\ItemSet::class,
                'Omeka\Entity\Item' => \Omeka\Entity\Item::class,
                'Omeka\Entity\Media' => \Omeka\Entity\Media::class,
                'Omeka\Entity\Resource' => '',
            ];
            if (!isset($resourceTypes[$resourceType])) {
                return $isSingle ? null : [];
            }
            $resourceType = $resourceTypes[$resourceType];
        }

        switch ($identifierType) {
            case 'internal_id':
                $result = $this->findResourcesFromInternalIds($identifiers, $resourceType);
                break;
            case 'property':
                $result = $this->findResourcesFromPropertyIds($identifiers, $identifierName, $resourceType);
                break;
            case 'media_source':
                $result = $this->findResourcesFromMediaSource($identifiers, $identifierName, $itemId);
                break;
        }

        return $isSingle ? ($result ? reset($result) : null) : $result;
    }

    protected function findResourcesFromInternalIds($identifiers, $resourceType)
    {
        // The api manager doesn't manage this type of search.
        $conn = $this->connection;
        $identifiers = array_map('intval', $identifiers);
        $quotedIdentifiers = implode(',', $identifiers);
        $qb = $conn->createQueryBuilder()
            ->select('resource.id')
            ->from('resource', 'resource')
            // ->andWhere('resource.id in (:ids)')
            // ->setParameter(':ids', $identifiers)
            ->andWhere("resource.id in ($quotedIdentifiers)")
            ->addOrderBy('resource.id', 'ASC');
        if ($resourceType) {
            $qb
                ->andWhere('resource.resource_type = :resource_type')
                ->setParameter(':resource_type', $resourceType);
        }
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Reorder the result according to the input (simpler in php and there
        // is no duplicated identifiers).
        return array_replace(array_fill_keys($identifiers, null), array_combine($result, $result));
    }

    protected function findResourcesFromPropertyIds($identifiers, $identifierPropertyId, $resourceType)
    {
        // The api manager doesn't manage this type of search.
        $conn = $this->connection;

        // Search in multiple resource types in one time.
        $quotedIdentifiers = array_map([$conn, 'quote'], $identifiers);
        $quotedIdentifiers = implode(',', $quotedIdentifiers);
        $qb = $conn->createQueryBuilder()
            ->select('value.value as identifier', 'value.resource_id as id')
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'value.resource_id = resource.id')
            ->andwhere('value.property_id = :property_id')
            ->setParameter(':property_id', $identifierPropertyId)
            // ->andWhere('value.value in (:values)')
            // ->setParameter(':values', $identifiers)
            ->andWhere("value.value in ($quotedIdentifiers)")
            ->addOrderBy('resource.id', 'ASC')
            ->addOrderBy('value.id', 'ASC');
        if ($resourceType) {
            $qb
                ->andWhere('resource.resource_type = :resource_type')
                ->setParameter(':resource_type', $resourceType);
        }
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        // $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) cannot be used, because it
        // replaces the first id by later ids in case of true duplicates.
        $result = $stmt->fetchAll();

        return $this->cleanResult($identifiers, $result);
    }

    protected function findResourcesFromMediaSource($identifiers, $ingesterName, $itemId = null)
    {
        // The api manager doesn't manage this type of search.
        $conn = $this->connection;

        // Search in multiple resource types in one time.
        $quotedIdentifiers = array_map([$conn, 'quote'], $identifiers);
        $quotedIdentifiers = implode(',', $quotedIdentifiers);
        $qb = $conn->createQueryBuilder()
            ->select('media.source as identifier', 'media.id as id')
            ->from('media', 'media')
            ->andwhere('media.ingester = :ingester')
            ->setParameter(':ingester', $ingesterName)
            // ->andWhere('media.source in (:sources)')
            // ->setParameter(':sources', $identifiers)
            ->andwhere("media.source in ($quotedIdentifiers)")
            ->addOrderBy('media.id', 'ASC');
        if ($itemId) {
            $qb
                ->andWhere('media.item_id = :item_id')
                ->setParameter(':item_id', $itemId);
        }
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        // $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) cannot be used, because it
        // replaces the first id by later ids in case of true duplicates.
        $result = $stmt->fetchAll();

        return $this->cleanResult($identifiers, $result);
    }

    /**
     * Reorder the result according to the input (simpler in php and there is no
     * duplicated identifiers). When there are true duplicates, it returns the
     * first. When there are case insensitive duplicates, it returns the first
     * too.
     *
     * @param array $identifiers
     * @param array $result
     * @return array
     */
    protected function cleanResult(array $identifiers, array $result)
    {
        $cleanedResult = array_fill_keys($identifiers, null);

        // Prepare the lowercase result one time only.
        $lowerResult = array_map(function ($v) {
            return ['identifier' => strtolower($v['identifier']), 'id' => $v['id']];
        }, $result);

        foreach ($cleanedResult as $key => $value) {
            // Look for the first case sensitive result.
            foreach ($result as $resultValue) {
                if ($resultValue['identifier'] == $key) {
                    $cleanedResult[$key] = $resultValue['id'];
                    continue 2;
                }
            }
            // Look for the first case insensitive result.
            $lowerKey = strtolower($key);
            foreach ($lowerResult as $resultValue) {
                if ($resultValue['identifier'] == $lowerKey) {
                    $cleanedResult[$key] = $resultValue['id'];
                    continue 2;
                }
            }
        }

        return $cleanedResult;
    }
}
