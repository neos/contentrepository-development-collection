<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Domain\Repository;

/*
 * This file is part of the Neos.ContentGraph.RedisGraphAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\Graph;
use Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeAggregate;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;

/**
 * The Doctrine DBAL adapter content graph
 *
 * To be used as a read-only source of nodes
 *
 * @Flow\Scope("singleton")
 * @api
 */
final class ContentGraph implements ContentGraphInterface
{
    /**
     * @Flow\Inject
     * @var RedisClient
     */
    protected $redisClient;

    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var array|ContentSubgraphInterface[]
     */
    protected $subgraphs;

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @return ContentSubgraphInterface|null
     */
    final public function getSubgraphByIdentifier(
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint,
        Domain\Context\Parameters\VisibilityConstraints $visibilityConstraints
    ): ?ContentSubgraphInterface {
        $index = (string)$contentStreamIdentifier . '-' . $dimensionSpacePoint->getHash() . '-' . $visibilityConstraints->getHash();
        if (!isset($this->subgraphs[$index])) {
            $this->subgraphs[$index] = new ContentSubgraph($contentStreamIdentifier, $dimensionSpacePoint, $visibilityConstraints);
        }

        return $this->subgraphs[$index];
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param OriginDimensionSpacePoint $originDimensionSpacePoint
     * @return NodeInterface|null
     * @throws DBALException
     */
    public function findNodeByIdentifiers(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        OriginDimensionSpacePoint $originDimensionSpacePoint
    ): ?NodeInterface {
        throw new \RuntimeException("TODO implement");
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeTypeName $nodeTypeName
     * @throws DBALException
     * @throws \Exception
     * @return NodeAggregate|null
     */
    public function findRootNodeAggregateByType(ContentStreamIdentifier $contentStreamIdentifier, NodeTypeName $nodeTypeName): NodeAggregate
    {
        throw new \RuntimeException("TODO implement");
    }

    public function findNodeAggregatesByType(ContentStreamIdentifier $contentStreamIdentifier, NodeTypeName $nodeTypeName): \Iterator
    {
        throw new \RuntimeException("TODO implement");
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @return NodeAggregate|null
     * @throws DBALException
     * @throws \Exception
     */
    public function findNodeAggregateByIdentifier(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): ?NodeAggregate {

        $nodeRows = $this->redisClient->getGraphForReading($contentStreamIdentifier)->executeAndGet("
            MATCH
                ()
                    -[h:HIERARCHY]->
                (n:Node {nodeAggregateIdentifier: '{$nodeAggregateIdentifier->jsonSerialize()}'})
            RETURN
                n.originDimensionSpacePoint,
                n.nodeAggregateIdentifier,
                n.nodeTypeName,
                n.properties,
                n.classification,
                h.name,
                h.dimensionSpacePoint
        ");

        // TODO: disabled handling?? -> vermutlich OPTIONAL MATCH notwendig? -> disableddimensionspacepointhash

        return $this->nodeFactory->mapNodeRowsToNodeAggregate($contentStreamIdentifier, $nodeRows);
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @return array|NodeAggregate[]
     * @throws DBALException
     * @throws \Exception
     */
    public function findParentNodeAggregates(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): array {
        throw new \RuntimeException("TODO implement");
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $childNodeAggregateIdentifier
     * @param OriginDimensionSpacePoint $childOriginDimensionSpacePoint
     * @return NodeAggregate|null
     * @throws DBALException
     * @throws \Exception
     */
    public function findParentNodeAggregateByChildOriginDimensionSpacePoint(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $childNodeAggregateIdentifier,
        OriginDimensionSpacePoint $childOriginDimensionSpacePoint
    ): ?NodeAggregate {
        throw new \RuntimeException("TODO implement");
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @return array|NodeAggregate[]
     * @throws DBALException
     * @throws \Exception
     */
    public function findChildNodeAggregates(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier
    ): array {
        throw new \RuntimeException("TODO implement");
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param NodeName $name
     * @return array
     * @throws DBALException
     */
    public function findChildNodeAggregatesByName(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeName $name
    ): array {
        throw new \RuntimeException("TODO implement");
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @return array|NodeAggregate[]
     * @throws DBALException
     */
    public function findTetheredChildNodeAggregates(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier
    ): array {
        throw new \RuntimeException("TODO implement");
    }

    private function createChildNodeAggregateQuery(): string
    {
        return 'SELECT c.*,
                      ch.name, ch.contentstreamidentifier, ch.dimensionspacepoint AS covereddimensionspacepoint,
                      r.dimensionspacepointhash AS disableddimensionspacepointhash
                      FROM neos_contentgraph_node p
                      JOIN neos_contentgraph_hierarchyrelation ph ON ph.childnodeanchor = p.relationanchorpoint
                      JOIN neos_contentgraph_hierarchyrelation ch ON ch.parentnodeanchor = p.relationanchorpoint
                      JOIN neos_contentgraph_node c ON ch.childnodeanchor = c.relationanchorpoint
                      LEFT JOIN neos_contentgraph_restrictionrelation r
                          ON r.originnodeaggregateidentifier = p.nodeaggregateidentifier
                          AND r.contentstreamidentifier = ph.contentstreamidentifier
                          AND r.affectednodeaggregateidentifier = p.nodeaggregateidentifier
                          AND r.dimensionspacepointhash = ph.dimensionspacepointhash
                      WHERE p.nodeaggregateidentifier = :parentNodeAggregateIdentifier
                      AND ph.contentstreamidentifier = :contentStreamIdentifier
                      AND ch.contentstreamidentifier = :contentStreamIdentifier';
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeName $nodeName
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param OriginDimensionSpacePoint $parentNodeOriginDimensionSpacePoint
     * @param DimensionSpacePointSet $dimensionSpacePointsToCheck
     * @return DimensionSpacePointSet
     * @throws DBALException
     */
    public function getDimensionSpacePointsOccupiedByChildNodeName(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeName $nodeName,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        OriginDimensionSpacePoint $parentNodeOriginDimensionSpacePoint,
        DimensionSpacePointSet $dimensionSpacePointsToCheck
    ): DimensionSpacePointSet {
        $connection = $this->client->getConnection();

        $query = 'SELECT h.dimensionspacepoint, h.dimensionspacepointhash FROM neos_contentgraph_hierarchyrelation h
                      INNER JOIN neos_contentgraph_node n ON h.parentnodeanchor = n.relationanchorpoint
                      INNER JOIN neos_contentgraph_hierarchyrelation ph ON ph.childnodeanchor = n.relationanchorpoint
                      WHERE n.nodeaggregateidentifier = :parentNodeAggregateIdentifier
                      AND n.origindimensionspacepointhash = :parentNodeOriginDimensionSpacePointHash
                      AND ph.contentstreamidentifier = :contentStreamIdentifier
                      AND h.contentstreamidentifier = :contentStreamIdentifier
                      AND h.dimensionspacepointhash IN (:dimensionSpacePointHashes)
                      AND h.name = :nodeName';
        $parameters = [
            'parentNodeAggregateIdentifier' => (string)$parentNodeAggregateIdentifier,
            'parentNodeOriginDimensionSpacePointHash' => $parentNodeOriginDimensionSpacePoint->getHash(),
            'contentStreamIdentifier' => (string) $contentStreamIdentifier,
            'dimensionSpacePointHashes' => $dimensionSpacePointsToCheck->getPointHashes(),
            'nodeName' => (string) $nodeName
        ];
        $types = [
            'dimensionSpacePointHashes' => Connection::PARAM_STR_ARRAY
        ];
        $dimensionSpacePoints = [];
        foreach ($connection->executeQuery($query, $parameters, $types)->fetchAll() as $hierarchyRelationData) {
            $dimensionSpacePoints[$hierarchyRelationData['dimensionspacepointhash']] = new DimensionSpacePoint(json_decode($hierarchyRelationData['dimensionspacepoint'], true));
        }

        return new DimensionSpacePointSet($dimensionSpacePoints);
    }

    public function countNodes(): int
    {
        $connection = $this->client->getConnection();
        $query = 'SELECT COUNT(*) FROM neos_contentgraph_node';

        return (int) $connection->executeQuery($query)->fetch()['COUNT(*)'];
    }

    /**
     * Returns all content stream identifiers
     *
     * @return ContentStreamIdentifier[]
     */
    public function findContentStreamIdentifiers(): array
    {
        $connection = $this->client->getConnection();

        $rows = $connection->executeQuery('SELECT DISTINCT contentstreamidentifier FROM neos_contentgraph_hierarchyrelation')->fetchAll();
        return array_map(function (array $row) {
            return ContentStreamIdentifier::fromString($row['contentstreamidentifier']);
        }, $rows);
    }

    public function enableCache(): void
    {
        if (is_array($this->subgraphs)) {
            foreach ($this->subgraphs as $subgraph) {
                $subgraph->getInMemoryCache()->enable();
            }
        }
    }

    public function disableCache(): void
    {
        if (is_array($this->subgraphs)) {
            foreach ($this->subgraphs as $subgraph) {
                $subgraph->getInMemoryCache()->disable();
            }
        }
    }
}
