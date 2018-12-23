<?php

/*
 * Copyright 2017-2019 Daniel Berthereau
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
use Omeka\Mvc\Controller\Plugin\Api;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Copy of the controller plugin of the module Csv Import
 *
 * @see \CSVImport\Mvc\Controller\Plugin\FindResourcesFromIdentifiers
 */
class FindResourcesFromIdentifiers extends AbstractPlugin
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @param Connection $connection
     * @param Api $api
     */
    public function __construct(Connection $connection, Api $api)
    {
        $this->connection = $connection;
        $this->api = $api;
    }

    /**
     * Find a list of resource ids from a list of identifiers (or one id).
     *
     * When there are true duplicates and case insensitive duplicates, the first
     * case sensitive is returned, else the first case insensitive resource.
     *
     * @todo Manage Media source html.
     *
     * @param array|string $identifiers Identifiers should be unique. If a
     * string is sent, the result will be the resource.
     * @param string|int|array $identifierName Property as integer or term,
     * "o:id", a media ingester (url or file), or an associative array with
     * multiple conditions (for media source). May be a list of identifier
     * metadata names, in which case the identifiers are searched in a list of
     * properties and/or in internal ids.
     * @param string $resourceType The resource type if any.
     * @return array|int|null Associative array with the identifiers as key and the ids
     * or null as value. Order is kept, but duplicate identifiers are removed.
     * If $identifiers is a string, return directly the resource id, or null.
     */
    public function __invoke($identifiers, $identifierName, $resourceType = null)
    {
        $isSingle = is_string($identifiers);

        if (empty($identifierName)) {
            return $isSingle ? null : [];
        }

        if ($isSingle) {
            $identifiers = [$identifiers];
        }
        $identifiers = array_unique(array_filter(array_map(function ($v) {
            return $this->trimUnicode($v);
        }, $identifiers)));
        if (empty($identifiers)) {
            return $isSingle ? null : [];
        }

        $args = $this->normalizeArgs($identifierName, $resourceType);
        if (empty($args)) {
            return $isSingle ? null : [];
        }
        list($identifierTypeNames, $resourceType, $itemId) = $args;

        foreach ($identifierTypeNames as $identifierType => $identifierName) {
            $result = $this->findResources($identifierType, $identifiers, $identifierName, $resourceType, $itemId);
            if ($result) {
                return $isSingle ? reset($result) : $result;
            }
        }
        return $isSingle ? null : [];
    }

    protected function findResources($identifierType, $identifiers, $identifierName, $resourceType, $itemId)
    {
        switch ($identifierType) {
            case 'o:id':
                return $this->findResourcesFromInternalIds($identifiers, $resourceType);
            case 'property':
                return $this->findResourcesFromPropertyIds($identifiers, $identifierName, $resourceType);
            case 'media_source':
                return $this->findResourcesFromMediaSource($identifiers, $identifierName, $itemId);
        }
    }

    protected function normalizeArgs($identifierName, $resourceType)
    {
        $identifierType = null;
        $identifierTypeName = null;
        $itemId = null;

        // Process identifier metadata names as an array.
        if (is_array($identifierName)) {
            if (isset($identifierName['o:ingester'])) {
                // TODO Currently, the media source cannot be html.
                if ($identifierName['o:ingester'] === 'html') {
                    return;
                }
                $identifierType = 'media_source';
                $identifierTypeName = $identifierName['o:ingester'];
                $resourceType = 'media';
                $itemId = empty($identifierName['o:item']['o:id']) ? null : $identifierName['o:item']['o:id'];
            } else {
                return $this->normalizeMultipleIdentifierMetadata($identifierName, $resourceType);
            }
        }
        // Next, identifierName is a string or an integer.
        elseif ($identifierName === 'o:id') {
            $identifierType = 'o:id';
            $identifierTypeName = 'o:id';
        } elseif (is_numeric($identifierName)) {
            $identifierType = 'property';
            // No check of the property id for quicker process.
            $identifierTypeName = (int) $identifierName;
        } else {
            $property = $this->api
                ->searchOne('properties', ['term' => $identifierName])->getContent();
            if ($property) {
                $identifierType = 'property';
                $identifierTypeName = $property->id();
            } elseif (in_array($identifierName, ['url', 'file'])) {
                $identifierType = 'media_source';
                $identifierTypeName = $identifierName;
                $resourceType = 'media';
                $itemId = null;
            }
        }
        if (empty($identifierTypeName)) {
            return;
        }

        if ($resourceType) {
            $resourceType = $this->normalizeResourceType($resourceType);
            if (empty($resourceType)) {
                return;
            }
        }

        return [
            [$identifierType => $identifierTypeName],
            $resourceType,
            $itemId,
        ];
    }

    protected function normalizeMultipleIdentifierMetadata($identifierNames, $resourceType)
    {
        $identifierTypeNames = [];
        foreach ($identifierNames as $identifierName) {
            $args = $this->normalizeArgs($identifierName, $resourceType);
            if ($args) {
                list($identifierTypeName) = $args;
                $identifierName = reset($identifierTypeName);
                $identifierType = key($identifierTypeName);
                switch ($identifierType) {
                    case 'o:id':
                        $identifierTypeNames[$identifierType] = $identifierName;
                        break;
                    default:
                        $identifierTypeNames[$identifierType][] = $identifierName;
                        break;
                }
            }
        }
        if (!$identifierTypeNames) {
            return;
        }

        if ($resourceType) {
            $resourceType = $this->normalizeResourceType($resourceType);
            if (empty($resourceType)) {
                return;
            }
        }

        return [
            $identifierTypeNames,
            $resourceType,
            null,
        ];
    }

    protected function normalizeResourceType($resourceType)
    {
        $resourceTypes = [
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'resources' => '',
            // Avoid a check and make the plugin more flexible.
            \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
            \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
            \Omeka\Entity\Resource::class => '',
            'o:item' => \Omeka\Entity\Item::class,
            'o:item_set' => \Omeka\Entity\ItemSet::class,
            'o:media' => \Omeka\Entity\Media::class,
        ];
        return isset($resourceTypes[$resourceType])
            ? $resourceTypes[$resourceType]
            : null;
    }

    protected function findResourcesFromInternalIds($identifiers, $resourceType)
    {
        $identifiers = array_filter(array_map('intval', $identifiers));
        if (empty($identifiers)) {
            return [];
        }

        // The api manager doesn't manage this type of search.
        $conn = $this->connection;

        $quotedIdentifiers = implode(',', $identifiers);
        $qb = $conn->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('resource.id')
            ->from('resource', 'resource')
            // ->andWhere($expr->in('resource.id', ':ids'))
            // ->setParameter('ids', $identifiers)
            ->andWhere('resource.id IN (:ids)')
            ->setParameter('ids', $quotedIdentifiers)
            ->addOrderBy('resource.id', 'ASC');
        if ($resourceType) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':resource_type'))
                ->setParameter('resource_type', $resourceType);
        }
        $stmt = $conn->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Reorder the result according to the input (simpler in php and there
        // is no duplicated identifiers).
        return array_replace(array_fill_keys($identifiers, null), array_combine($result, $result));
    }

    protected function findResourcesFromPropertyIds($identifiers, $propertyIds, $resourceType)
    {
        // The api manager doesn't manage this type of search.
        $conn = $this->connection;

        // Search in multiple resource types in one time.
        $quotedIdentifiers = array_map([$conn, 'quote'], $identifiers);
        $quotedIdentifiers = implode(',', $quotedIdentifiers);
        $qb = $conn->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('value.value AS identifier', 'value.resource_id AS id')
            ->from('value', 'value')
            ->leftJoin('value', 'resource', 'resource', 'value.resource_id = resource.id')
            // ->andwhere($expr->in('value.property_id', ':property_ids'))
            // ->setParameter('property_ids', implode(',', $propertyIds))
            ->andwhere('value.property_id IN (:property_ids)')
            ->setParameter('property_ids', implode(',', $propertyIds))
            // ->andWhere($expr->in('value.value', ':values'))
            // ->setParameter('values', $quotedIdentifiers)
            // ->andWhere('value.value IN (:values)')
            // ->setParameter('values', $identifiers)
            ->andWhere("value.value IN ($quotedIdentifiers)")
            ->addOrderBy('resource.id', 'ASC')
            ->addOrderBy('value.id', 'ASC');
        if ($resourceType) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':resource_type'))
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
        $qb = $conn->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('media.source AS identifier', 'media.id AS id')
            ->from('media', 'media')
            ->andwhere('media.ingester = :ingester')
            ->setParameter('ingester', $ingesterName)
            // ->andWhere('media.source IN (:sources)')
            // ->setParameter('sources', $identifiers)
            // ->andWhere($expr->in('media.source', ':sources'))
            // ->setParameter('sources', $quotedIdentifiers)
            ->andwhere("media.source IN ($quotedIdentifiers)")
            ->addOrderBy('media.id', 'ASC');
        if ($itemId) {
            $qb
                ->andWhere($expr->eq('media.item_id', ':item_id'))
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

        foreach (array_keys($cleanedResult) as $key) {
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

    /**
     * Trim all whitespaces.
     *
     * @param string $string
     * @return string
     */
    protected function trimUnicode($string)
    {
        return preg_replace('/^[\h\v\s[:blank:][:space:]]+|[\h\v\s[:blank:][:space:]]+$/u', '', $string);
    }
}
