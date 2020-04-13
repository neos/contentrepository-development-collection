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
use Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
use Neos\EventSourcedContentRepository\Domain as ContentRepository;
use Neos\EventSourcedContentRepository\Domain\Context\ContentSubgraph\SubtreeInterface;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraints;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Unicode\Functions as UnicodeFunctions;

/**
 * The content subgraph application repository
 *
 * To be used as a read-only source of nodes.
 *
 *
 *
 * ## Conventions for SQL queries
 *
 * - n -> node
 * - h -> hierarchy edge
 *
 * - if more than one node (parent-child)
 *   - pn -> parent node
 *   - cn -> child node
 *   - h -> the hierarchy edge connecting parent and child
 *   - ph -> the hierarchy edge incoming to the parent (sometimes relevant)
 *
 *
 * @api
 */
final class ContentSubgraph implements ContentSubgraphInterface
{
    /**
     * @Flow\Inject
     * @var RedisClient
     */
    protected $redisClient;

    /**
     * @Flow\Inject
     * @var NodeTypeConstraintFactory
     */
    protected $nodeTypeConstraintFactory;

    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @var InMemoryCache
     */
    protected $inMemoryCache;

    /**
     * @var ContentStreamIdentifier
     */
    protected $contentStreamIdentifier;

    /**
     * @var DimensionSpacePoint
     */
    protected $dimensionSpacePoint;

    /**
     * @var ContentRepository\Context\Parameters\VisibilityConstraints
     */
    protected $visibilityConstraints;

    public function __construct(ContentStreamIdentifier $contentStreamIdentifier, DimensionSpacePoint $dimensionSpacePoint, ContentRepository\Context\Parameters\VisibilityConstraints $visibilityConstraints)
    {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->dimensionSpacePoint = $dimensionSpacePoint;
        $this->visibilityConstraints = $visibilityConstraints;
        $this->inMemoryCache = new InMemoryCache();
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    public function getDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->dimensionSpacePoint;
    }

    /**
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return array|NodeInterface[]
     * @throws \Exception
     */
    public function findChildNodes(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): array
    {
        if ($limit !== null || $offset !== null) {
            throw new \RuntimeException("TODO: Limit/Offset not yet supported in findChildNodes");
        }

        $cache = $this->inMemoryCache->getAllChildNodesByNodeIdentifierCache();
        $namedChildNodeCache = $this->inMemoryCache->getNamedChildNodeByNodeIdentifierCache();
        $parentNodeIdentifierCache = $this->inMemoryCache->getParentNodeIdentifierByChildNodeIdentifierCache();

        if ($cache->contains($nodeAggregateIdentifier, $nodeTypeConstraints)) {
            return $cache->findChildNodes($nodeAggregateIdentifier, $nodeTypeConstraints, $limit, $offset);
        }
        $query = new SqlQueryBuilder();
        $query->addToQuery('
-- ContentSubgraph::findChildNodes
SELECT c.*, h.name, h.contentstreamidentifier FROM neos_contentgraph_node p
 INNER JOIN neos_contentgraph_hierarchyrelation h ON h.parentnodeanchor = p.relationanchorpoint
 INNER JOIN neos_contentgraph_node c ON h.childnodeanchor = c.relationanchorpoint
 WHERE p.nodeaggregateidentifier = :parentNodeAggregateIdentifier
 AND h.contentstreamidentifier = :contentStreamIdentifier
 AND h.dimensionspacepointhash = :dimensionSpacePointHash')
            ->parameter('parentNodeAggregateIdentifier', $nodeAggregateIdentifier)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());

        self::addNodeTypeConstraintsToQuery($query, $nodeTypeConstraints);
        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'c');
        $query->addToQuery('ORDER BY h.position ASC');

        $result = [];
        foreach ($query->execute($this->getDatabaseConnection())->fetchAll() as $nodeData) {
            $node = $this->nodeFactory->mapNodeRowToNode($nodeData);
            $result[] = $node;
            $namedChildNodeCache->add($nodeAggregateIdentifier, $node->getNodeName(), $node);
            $parentNodeIdentifierCache->add($node->getNodeAggregateIdentifier(), $nodeAggregateIdentifier);
            $this->inMemoryCache->getNodeByNodeAggregateIdentifierCache()->add($node->getNodeAggregateIdentifier(), $node);
        }

        if ($limit === null && $offset === null) {
            $cache->add($nodeAggregateIdentifier, $nodeTypeConstraints, $result);
        }

        return $result;
    }

    public function findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier $nodeAggregateIdentifier): ?NodeInterface
    {
        $cache = $this->inMemoryCache->getNodeByNodeAggregateIdentifierCache();

        if ($cache->knowsAbout($nodeAggregateIdentifier)) {
            return $cache->get($nodeAggregateIdentifier);
        } else {
            $result = $this->redisClient->getGraphForReading($this->getContentStreamIdentifier())
                ->executeAndGet("
                MATCH
                    ()
                        -[h:HIERARCHY {dimensionSpacePointHash: '{$this->getDimensionSpacePoint()->getHash()}'}]->
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
            // TODO
            //$query = self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints);

            if (!count($result)) {
                $cache->rememberNonExistingNodeAggregateIdentifier($nodeAggregateIdentifier);
                return null;
            }

            $node = $this->nodeFactory->mapNodeRowToNode($this->getContentStreamIdentifier(), $result[0]);
            $cache->add($nodeAggregateIdentifier, $node);

            return $node;
        }
    }

    private static function addRestrictionRelationConstraintsToQuery(SqlQueryBuilder $query, ContentRepository\Context\Parameters\VisibilityConstraints $visibilityConstraints, string $aliasOfNodeInQuery = 'n', string $aliasOfHierarchyEdgeInQuery = 'h', $markerToReplaceInQuery = null): SqlQueryBuilder
    {
        // TODO: make QueryBuilder immutable
        if (!$visibilityConstraints->isDisabledContentShown()) {
            $query->addToQuery('
                and not exists (
                    select
                        1
                    from
                        neos_contentgraph_restrictionrelation r
                    where
                        r.contentstreamidentifier = ' . $aliasOfHierarchyEdgeInQuery . '.contentstreamidentifier
                        and r.dimensionspacepointhash = ' . $aliasOfHierarchyEdgeInQuery . '.dimensionspacepointhash
                        and r.affectednodeaggregateidentifier = ' . $aliasOfNodeInQuery . '.nodeaggregateidentifier
                )', $markerToReplaceInQuery);
        } else {
            $query->addToQuery('', $markerToReplaceInQuery);
        }

        return $query;
    }

    public function countChildNodes(
        NodeAggregateIdentifier $parentNodeNodeAggregateIdentifier,
        NodeTypeConstraints $nodeTypeConstraints = null
    ): int
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery('SELECT COUNT(*) FROM neos_contentgraph_node p
 INNER JOIN neos_contentgraph_hierarchyrelation h ON h.parentnodeanchor = p.relationanchorpoint
 INNER JOIN neos_contentgraph_node c ON h.childnodeanchor = c.relationanchorpoint
 WHERE p.nodeaggregateidentifier = :parentNodeNodeAggregateIdentifier
 AND h.contentstreamidentifier = :contentStreamIdentifier
 AND h.dimensionspacepointhash = :dimensionSpacePointHash')
            ->parameter('parentNodeNodeAggregateIdentifier', (string)$parentNodeNodeAggregateIdentifier)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());

        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'c');

        if ($nodeTypeConstraints) {
            self::addNodeTypeConstraintsToQuery($query, $nodeTypeConstraints);
        }

        $res = $query->execute($this->getDatabaseConnection())->fetchColumn(0);
        return (int)$res;
    }

    /**
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param PropertyName $name
     * @return NodeInterface[]
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findReferencedNodes(NodeAggregateIdentifier $nodeAggregateIdentifier, PropertyName $name = null): array
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery('
-- ContentSubgraph::findReferencedNodes
SELECT d.*, dh.contentstreamidentifier, dh.name FROM neos_contentgraph_hierarchyrelation sh
 INNER JOIN neos_contentgraph_node s ON sh.childnodeanchor = s.relationanchorpoint
 INNER JOIN neos_contentgraph_referencerelation r ON s.relationanchorpoint = r.nodeanchorpoint
 INNER JOIN neos_contentgraph_node d ON r.destinationnodeaggregateidentifier = d.nodeaggregateidentifier
 INNER JOIN neos_contentgraph_hierarchyrelation dh ON dh.childnodeanchor = d.relationanchorpoint
 WHERE s.nodeaggregateidentifier = :nodeAggregateIdentifier
 AND dh.dimensionspacepointhash = :dimensionSpacePointHash
 AND sh.dimensionspacepointhash = :dimensionSpacePointHash
 AND dh.contentstreamidentifier = :contentStreamIdentifier
 AND sh.contentstreamidentifier = :contentStreamIdentifier
'
        )
            ->parameter('nodeAggregateIdentifier', (string)$nodeAggregateIdentifier)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', (string)$this->getDimensionSpacePoint()->getHash())
            ->parameter('name', (string)$name);

        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'd', 'dh');

        if ($name) {
            $query->addToQuery('
 AND r.name = :name
 ORDER BY r.position'
            );
        } else {
            $query->addToQuery('
 ORDER BY r.name, r.position'
            );
        }

        $result = [];
        foreach ($query->execute($this->getDatabaseConnection())->fetchAll() as $nodeData) {
            $result[] = $this->nodeFactory->mapNodeRowToNode($nodeData);
        }

        return $result;
    }

    /**
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param PropertyName $name
     * @return NodeInterface[]
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findReferencingNodes(NodeAggregateIdentifier $nodeAggregateIdentifier, PropertyName $name = null): array
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery(
            '
-- ContentSubgraph::findReferencingNodes
SELECT s.*, sh.contentstreamidentifier, sh.name FROM neos_contentgraph_hierarchyrelation sh
 INNER JOIN neos_contentgraph_node s ON sh.childnodeanchor = s.relationanchorpoint
 INNER JOIN neos_contentgraph_referencerelation r ON s.relationanchorpoint = r.nodeanchorpoint
 INNER JOIN neos_contentgraph_node d ON r.destinationnodeaggregateidentifier = d.nodeaggregateidentifier
 INNER JOIN neos_contentgraph_hierarchyrelation dh ON dh.childnodeanchor = d.relationanchorpoint
 WHERE d.nodeaggregateidentifier = :destinationnodeaggregateidentifier
 AND dh.dimensionspacepointhash = :dimensionSpacePointHash
 AND sh.dimensionspacepointhash = :dimensionSpacePointHash
 AND dh.contentstreamidentifier = :contentStreamIdentifier
 AND sh.contentstreamidentifier = :contentStreamIdentifier
'
        )
            ->parameter('destinationnodeaggregateidentifier', (string)$nodeAggregateIdentifier)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', (string)$this->getDimensionSpacePoint()->getHash())
            ->parameter('name', (string)$name);

        if ($name) {
            $query->addToQuery('AND r.name = :name');
        }

        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 's', 'sh');

        $result = [];
        foreach ($query->execute($this->getDatabaseConnection())->fetchAll() as $nodeData) {
            $result[] = $this->nodeFactory->mapNodeRowToNode($nodeData);
        }

        return $result;
    }

    /**
     * @param NodeAggregateIdentifier $childNodeAggregateIdentifier
     * @return NodeInterface|null
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     * @throws \Neos\EventSourcedContentRepository\Exception\NodeConfigurationException
     * @throws \Neos\EventSourcedContentRepository\Exception\NodeTypeNotFoundException
     */
    public function findParentNode(NodeAggregateIdentifier $childNodeAggregateIdentifier): ?NodeInterface
    {
        $cache = $this->inMemoryCache->getParentNodeIdentifierByChildNodeIdentifierCache();

        if ($cache->knowsAbout($childNodeAggregateIdentifier)) {
            $possibleParentIdentifier = $cache->get($childNodeAggregateIdentifier);

            if ($possibleParentIdentifier === null) {
                return null;
            } else {
                // we here trigger findNodeByIdentifier, as this might retrieve the Parent Node from the in-memory cache if it has been loaded before
                return $this->findNodeByNodeAggregateIdentifier($possibleParentIdentifier);
            }
        }

        $result = $this->redisClient->getGraphForReading($this->getContentStreamIdentifier())
            ->executeAndGet("
                MATCH
                    () -[h:HIERARCHY {dimensionSpacePointHash: '{$this->getDimensionSpacePoint()->getHash()}'}]->(n),
                    (n:Node)
                        -[:HIERARCHY {dimensionSpacePointHash: '{$this->getDimensionSpacePoint()->getHash()}'}]->
                    (:Node {nodeAggregateIdentifier: '{$childNodeAggregateIdentifier->jsonSerialize()}'})
                RETURN
                    n.originDimensionSpacePoint,
                    n.nodeAggregateIdentifier,
                    n.nodeTypeName,
                    n.properties,
                    n.classification,
                    h.name,
                    h.dimensionSpacePoint
                ");
        // TODO
        //$query = self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'p');

        $nodeRow = count($result) > 0 ? $result[0] : null;
        $node = $nodeRow ? $this->nodeFactory->mapNodeRowToNode($this->getContentStreamIdentifier(), $nodeRow) : null;
        if ($node) {
            $cache->add($childNodeAggregateIdentifier, $node->getNodeAggregateIdentifier());

            // we also add the parent node to the NodeAggregateIdentifier => Node cache; as this might improve cache hit rates as well.
            $this->inMemoryCache->getNodeByNodeAggregateIdentifierCache()->add($node->getNodeAggregateIdentifier(), $node);
        } else {
            $cache->rememberNonExistingParentNode($childNodeAggregateIdentifier);
        }

        return $node;
    }

    /**
     * @param NodePath $path
     * @param NodeAggregateIdentifier $startingNodeAggregateIdentifier
     * @return NodeInterface|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findNodeByPath(NodePath $path, NodeAggregateIdentifier $startingNodeAggregateIdentifier): ?NodeInterface
    {
        $currentNode = $this->findNodeByNodeAggregateIdentifier($startingNodeAggregateIdentifier);
        if (!$currentNode) {
            throw new \RuntimeException('Starting Node (identified by ' . $startingNodeAggregateIdentifier . ') does not exist.');
        }
        foreach ($path->getParts() as $edgeName) {
            // identifier exists here :)
            $currentNode = $this->findChildNodeConnectedThroughEdgeName(
                $currentNode->getNodeAggregateIdentifier(),
                $edgeName
            );
            if (!$currentNode) {
                return null;
            }
        }

        return $currentNode;
    }

    /**
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param NodeName $edgeName
     * @return NodeInterface|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findChildNodeConnectedThroughEdgeName(
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeName $edgeName
    ): ?NodeInterface
    {
        $cache = $this->inMemoryCache->getNamedChildNodeByNodeIdentifierCache();
        if ($cache->contains($parentNodeAggregateIdentifier, $edgeName)) {
            return $cache->get($parentNodeAggregateIdentifier, $edgeName);
        } else {
            $query = new SqlQueryBuilder();
            $query->addToQuery(
                '
-- ContentGraph::findChildNodeConnectedThroughEdgeName
SELECT
    c.*,
    h.name,
    h.contentstreamidentifier
FROM
    neos_contentgraph_node p
INNER JOIN neos_contentgraph_hierarchyrelation h
    ON h.parentnodeanchor = p.relationanchorpoint
INNER JOIN neos_contentgraph_node c
    ON h.childnodeanchor = c.relationanchorpoint
WHERE
    p.nodeaggregateidentifier = :parentNodeAggregateIdentifier
    AND h.contentstreamidentifier = :contentStreamIdentifier
    AND h.dimensionspacepointhash = :dimensionSpacePointHash
    AND h.name = :edgeName'
            )
                ->parameter('parentNodeAggregateIdentifier', (string)$parentNodeAggregateIdentifier)
                ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
                ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash())
                ->parameter('edgeName', (string)$edgeName);

            self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'c');

            $query->addToQuery('ORDER BY h.position LIMIT 1');

            $nodeData = $query->execute($this->getDatabaseConnection())->fetch();

            if ($nodeData) {
                $node = $this->nodeFactory->mapNodeRowToNode($nodeData);
                if ($node) {
                    $cache->add($parentNodeAggregateIdentifier, $edgeName, $node);
                    $this->inMemoryCache->getNodeByNodeAggregateIdentifierCache()->add($node->getNodeAggregateIdentifier(), $node);

                    return $node;
                }
            }
        }

        return null;
    }

    /**
     * @param NodeAggregateIdentifier $sibling
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function findSiblings(NodeAggregateIdentifier $sibling, NodeTypeConstraints $nodeTypeConstraints = null, int $limit = null, int $offset = null): array
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery($this->getSiblingBaseQuery() . '
            AND n.nodeaggregateidentifier != :siblingNodeAggregateIdentifier')
            ->parameter('siblingNodeAggregateIdentifier', (string)$sibling)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());

        if ($nodeTypeConstraints) {
            self::addNodeTypeConstraintsToQuery($query);
        }
        $query->addToQuery(' ORDER BY h.position');
        if ($limit) {
            $query->addToQuery(' LIMIT ' . $limit);
        }
        if ($offset) {
            $query->addToQuery(' OFFSET ' . $offset);
        }

        $result = [];
        foreach ($query->execute($this->getDatabaseConnection())->fetchAll() as $nodeRecord) {
            $result[] = $this->nodeFactory->mapNodeRowToNode($nodeRecord);
        }

        return $result;
    }

    /**
     * @param NodeAggregateIdentifier $sibling
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return array|NodeInterface[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function findPrecedingSiblings(
        NodeAggregateIdentifier $sibling,
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): array
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery($this->getSiblingBaseQuery() . '
            AND n.nodeaggregateidentifier != :siblingNodeAggregateIdentifier')
            ->parameter('siblingNodeAggregateIdentifier', (string)$sibling)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());
        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints);

        $query->addToQuery('
    AND h.position < (
        SELECT sibh.position FROM neos_contentgraph_hierarchyrelation sibh
        INNER JOIN neos_contentgraph_node sib ON sibh.childnodeanchor = sib.relationanchorpoint
        WHERE sib.nodeaggregateidentifier = :siblingNodeAggregateIdentifier
        AND sibh.contentstreamidentifier = :contentStreamIdentifier AND sibh.dimensionspacepointhash = :dimensionSpacePointHash
    )');

        if ($nodeTypeConstraints) {
            self::addNodeTypeConstraintsToQuery($query);
        }
        $query->addToQuery(' ORDER BY h.position DESC');
        if ($limit) {
            $query->addToQuery(' LIMIT ' . $limit);
        }
        if ($offset) {
            $query->addToQuery(' OFFSET ' . $offset);
        }

        $result = [];
        foreach ($query->execute($this->getDatabaseConnection())->fetchAll() as $nodeRecord) {
            $result[] = $this->nodeFactory->mapNodeRowToNode($nodeRecord);
        }

        return $result;
    }

    /**
     * @param NodeAggregateIdentifier $sibling
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return array|NodeInterface[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function findSucceedingSiblings(
        NodeAggregateIdentifier $sibling,
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): array
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery($this->getSiblingBaseQuery() . '
            AND n.nodeaggregateidentifier != :siblingNodeAggregateIdentifier')
            ->parameter('siblingNodeAggregateIdentifier', (string)$sibling)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());
        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints);

        $query->addToQuery('
    AND h.position > (
        SELECT sibh.position FROM neos_contentgraph_hierarchyrelation sibh
        INNER JOIN neos_contentgraph_node sib ON sibh.childnodeanchor = sib.relationanchorpoint
        WHERE sib.nodeaggregateidentifier = :siblingNodeAggregateIdentifier
        AND sibh.contentstreamidentifier = :contentStreamIdentifier AND sibh.dimensionspacepointhash = :dimensionSpacePointHash
    )');

        if ($nodeTypeConstraints) {
            self::addNodeTypeConstraintsToQuery($query);
        }
        $query->addToQuery(' ORDER BY h.position ASC');
        if ($limit) {
            $query->addToQuery(' LIMIT ' . $limit);
        }
        if ($offset) {
            $query->addToQuery(' OFFSET ' . $offset);
        }

        $result = [];
        foreach ($query->execute($this->getDatabaseConnection())->fetchAll() as $nodeRecord) {
            $result[] = $this->nodeFactory->mapNodeRowToNode($nodeRecord);
        }

        return $result;
    }

    protected function getSiblingBaseQuery(): string
    {
        return '
  SELECT n.*, h.contentstreamidentifier, h.name FROM neos_contentgraph_node n
  INNER JOIN neos_contentgraph_hierarchyrelation h ON h.childnodeanchor = n.relationanchorpoint
  WHERE h.contentstreamidentifier = :contentStreamIdentifier AND h.dimensionspacepointhash = :dimensionSpacePointHash
  AND h.parentnodeanchor = (
      SELECT sibh.parentnodeanchor FROM neos_contentgraph_hierarchyrelation sibh
      INNER JOIN neos_contentgraph_node sib ON sibh.childnodeanchor = sib.relationanchorpoint
      WHERE sib.nodeaggregateidentifier = :siblingNodeAggregateIdentifier
      AND sibh.contentstreamidentifier = :contentStreamIdentifier AND sibh.dimensionspacepointhash = :dimensionSpacePointHash
  )';
    }

    protected function getDatabaseConnection(): Connection
    {
        return $this->client->getConnection();
    }


    public function findNodePath(NodeAggregateIdentifier $nodeAggregateIdentifier): NodePath
    {
        $cache = $this->inMemoryCache->getNodePathCache();

        if ($cache->contains($nodeAggregateIdentifier)) {
            return $cache->get($nodeAggregateIdentifier);
        }

        $result = $this->getDatabaseConnection()->executeQuery(
            '
            -- ContentSubgraph::findNodePath
            with recursive nodePath as (
            SELECT h.name, h.parentnodeanchor FROM neos_contentgraph_node n
                 INNER JOIN neos_contentgraph_hierarchyrelation h ON h.childnodeanchor = n.relationanchorpoint
                 AND h.contentstreamidentifier = :contentStreamIdentifier
                 AND h.dimensionspacepointhash = :dimensionSpacePointHash
                 AND n.nodeaggregateidentifier = :nodeAggregateIdentifier

            UNION

                SELECT h.name, h.parentnodeanchor FROM neos_contentgraph_hierarchyrelation h
                    INNER JOIN nodePath as np ON h.childnodeanchor = np.parentnodeanchor
                    WHERE
                        h.contentstreamidentifier = :contentStreamIdentifier
                        AND h.dimensionspacepointhash = :dimensionSpacePointHash

        )
        select * from nodePath',
            [
                'contentStreamIdentifier' => (string)$this->getContentStreamIdentifier(),
                'dimensionSpacePointHash' => $this->getDimensionSpacePoint()->getHash(),
                'nodeAggregateIdentifier' => (string)$nodeAggregateIdentifier
            ]
        )->fetchAll();

        $nodePathSegments = [];

        foreach ($result as $r) {
            $nodePathSegments[] = $r['name'];
        }

        $nodePathSegments = array_reverse($nodePathSegments);
        $nodePath = NodePath::fromPathSegments($nodePathSegments);
        $cache->add($nodeAggregateIdentifier, $nodePath);

        return $nodePath;
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'dimensionSpacePoint' => $this->dimensionSpacePoint
        ];
    }

    const SUBTREE_REDIS_QUERY = '
        ----------------------------------
        -- HELPERS: Parse RedisGraph results
        ----------------------------------

        -- Parse the property structure from https://oss.redislabs.com/redisgraph/result_structure/#nodes
        -- into a table (propertyName -> propertyValue)
        local parseProperties = function(properties)
            local parsedProperties = {}
            for i, row in pairs(properties) do
                local propertyName = row[1]
                local propertyValue = row[2]
                parsedProperties[propertyName] = propertyValue
            end

            return parsedProperties
        end

        -- Parse node properties
        local parseNodeProperties = function(node)
            assert(node[3][1] == "properties", "node properties assertion, found " .. node[3][1])
            local properties = node[3][2]

            return parseProperties(properties)
        end

        -- extract rows from graph result
        local getRows = function(queryResult)
            -- Index 1: Table Headers
            -- Index 2: Result Data
            -- Index 3: Statistics
            return queryResult[2]
        end

        -- extract first row from graph result
        local getFirstRow = function(queryResult)
            -- return the first row
            return getRows(queryResult)[1]
        end


        ----------------------------------
        -- RECURSIVE QUERY
        ----------------------------------
        local findChildNodes = nil
        findChildNodes = function(parentNodeAggregateIdentifier, levelsSoFar, maximumLevels, dimensionSpacePointHash, nodeTypeConstraintsQueryPart, graphName)
            if levelsSoFar >= maximumLevels then
                return {}
            end

            local rows = getRows(redis.call("GRAPH.QUERY", graphName, "MATCH () -[:HIERARCHY {dimensionSpacePointHash: \'" .. dimensionSpacePointHash .. "\'}]-> (:Node {nodeAggregateIdentifier: \'" .. parentNodeAggregateIdentifier .. "\'}) -[:HIERARCHY {dimensionSpacePointHash: \'" .. dimensionSpacePointHash .. "\'}] -> (node:Node) RETURN node"))
            local result = {}
            for i, row in ipairs(rows) do
                local node = parseNodeProperties(row[1])

                local childNodes = findChildNodes(node["nodeAggregateIdentifier"], levelsSoFar + 1, maximumLevels, dimensionSpacePointHash, nodeTypeConstraintsQueryPart, graphName)
                result[i] = {
                    node = node,
                    childNodes = childNodes
                }
            end

            return result
        end

        ----------------------------------
        -- INITIAL QUERY
        ----------------------------------
        local graphName = KEYS[1]
        local entryPointNodeAggregateIdentifiers = cjson.decode(ARGV[1]) -- list of root Node Aggregate Identifiers
        local nodeTypeConstraintsQueryPart = ARGV[2]
        local dimensionSpacePointHash = ARGV[3]
        local maximumLevels = ARGV[4]

        local result = {}
        for i, entryPointNodeAggregateIdentifier in ipairs(entryPointNodeAggregateIdentifiers) do
            local queryResult = redis.call("GRAPH.QUERY", graphName, "MATCH () -[:HIERARCHY {dimensionSpacePointHash: \'" .. dimensionSpacePointHash .. "\'}]-> (node:Node {nodeAggregateIdentifier: \'" .. entryPointNodeAggregateIdentifier .. "\'}) WHERE " .. nodeTypeConstraintsQueryPart .. " RETURN node")
            -- [1] is the "node" result (1st RETURN value)

            local row = getFirstRow(queryResult)
            if row then
                local node = parseNodeProperties(row[1])
                local childNodes = findChildNodes(node["nodeAggregateIdentifier"], 0, maximumLevels, dimensionSpacePointHash, nodeTypeConstraintsQueryPart, graphName)

                table.insert(result, {
                    node = node,
                    childNodes = childNodes
                })
            end
        end

        return cjson.encode(result)
    ';

    /**
     * @param array $entryNodeAggregateIdentifiers
     * @param int $maximumLevels
     * @param NodeTypeConstraints $nodeTypeConstraints
     * @return mixed|void
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Neos\ContentRepository\Exception\NodeConfigurationException
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    public function findSubtrees(
        array $entryNodeAggregateIdentifiers,
        int $maximumLevels,
        NodeTypeConstraints $nodeTypeConstraints
    ): SubtreeInterface
    {
        $graphName = $this->redisClient->getGraphName($this->getContentStreamIdentifier());
        $entryNodeAggregateIdentifiersAsStringArray = array_map(function (NodeAggregateIdentifier $nodeAggregateIdentifier) {
            return (string)$nodeAggregateIdentifier;
        }, $entryNodeAggregateIdentifiers);

        $result = $this->redisClient->getRedisClient()->eval(self::SUBTREE_REDIS_QUERY, [
            $graphName,
            json_encode($entryNodeAggregateIdentifiersAsStringArray),
            self::buildNodeTypeConstraintsCypherQueryPart($nodeTypeConstraints),
            $this->getDimensionSpacePoint()->getHash(),
            $maximumLevels
        ], 1);

        //self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'n', 'h', '###VISIBILITY_CONSTRAINTS_INITIAL###');
        //self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'c', 'h', '###VISIBILITY_CONSTRAINTS_RECURSION###');

        $result = json_decode($result);

        $subtree = new Subtree(0);
        foreach ($result as $resultElement) {
            $subtree->add($this->convertResultToSubtree($resultElement, 1, $subtree));
        }

        return $subtree;
    }

    private function convertResultToSubtree(array $result, int $level, SubtreeInterface $parentSubtree): SubtreeInterface
    {
        $node = $this->nodeFactory->mapNodeRowToNode($this->getContentStreamIdentifier(), $result['node']);
        $this->getInMemoryCache()->getNodeByNodeAggregateIdentifierCache()->add($node->getNodeAggregateIdentifier(), $node);

        $subtree = new Subtree($level, $node);
        if ($parentSubtree->getNode() !== null) {
            $this->getInMemoryCache()->getParentNodeIdentifierByChildNodeIdentifierCache()->add($node->getNodeAggregateIdentifier(), $parentSubtree->getNode()->getNodeAggregateIdentifier());
        }

        foreach ($result['childNodes'] as $childNodeResult) {
            $subtree->add($this->convertResultToSubtree($childNodeResult, $level + 1, $subtree));
        }

        return $subtree;
    }

    private static function arrayOfObjToCypherArray($input): string
    {
        $converted = [];
        foreach ($input as $value) {
            $converted[] = "'" . $value . "'";
        }

        return '[' . implode(', ', $converted) . ']';
    }

    private static function buildNodeTypeConstraintsCypherQueryPart(NodeTypeConstraints $nodeTypeConstraints, string $tableReference = 'n'): string
    {
        $concatenation = 'AND';

        if (!empty($nodeTypeConstraints->getExplicitlyAllowedNodeTypeNames())) {
            $allowanceQueryPart = $tableReference . '.nodeTypeName IN ' . self::arrayOfObjToCypherArray($nodeTypeConstraints->getExplicitlyAllowedNodeTypeNames());
        } else {
            $allowanceQueryPart = '';
        }
        if (!empty($nodeTypeConstraints->getExplicitlyDisallowedNodeTypeNames())) {
            $disAllowanceQueryPart = $tableReference . '.nodeTypeName NOT IN ' . self::arrayOfObjToCypherArray($nodeTypeConstraints->getExplicitlyDisallowedNodeTypeNames());
        } else {
            $disAllowanceQueryPart = '';
        }

        if ($allowanceQueryPart && $disAllowanceQueryPart) {
            return ' ' . $concatenation . ' (' . $allowanceQueryPart . ($nodeTypeConstraints->isWildcardAllowed() ? ' OR ' : ' AND ') . $disAllowanceQueryPart . ')';
        } elseif ($allowanceQueryPart && !$nodeTypeConstraints->isWildcardAllowed()) {
            return ' ' . $concatenation . ' ' . $allowanceQueryPart;
        } elseif ($disAllowanceQueryPart) {
            return ' ' . $concatenation . ' ' . $disAllowanceQueryPart;
        } else {
            return '';
        }
    }

    /**
     * @param array $entryNodeAggregateIdentifiers
     * @param NodeTypeConstraints $nodeTypeConstraints
     * @param ContentRepository\Projection\Content\SearchTerm|null $searchTerm
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Neos\ContentRepository\Exception\NodeConfigurationException
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     */
    public function findDescendants(array $entryNodeAggregateIdentifiers, NodeTypeConstraints $nodeTypeConstraints, ?ContentRepository\Projection\Content\SearchTerm $searchTerm): array
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery('
-- ContentSubgraph::findDescendants

-- we find all nodes matching the given constraints that are descendants of one of the given aggregates
with recursive tree as (
     -- --------------------------------
     -- INITIAL query: select the entry nodes
     -- --------------------------------
     select
     	n.*,
     	h.contentstreamidentifier,
     	h.name,

     	-- see https://mariadb.com/kb/en/library/recursive-common-table-expressions-overview/#cast-to-avoid-data-truncation
     	CAST("ROOT" AS CHAR(50)) as parentNodeAggregateIdentifier,
     	0 as level,
     	0 as position
     from
        neos_contentgraph_node n
     -- we need to join with the hierarchy relation, because we need the node name.
     INNER JOIN neos_contentgraph_hierarchyrelation h
        ON h.childnodeanchor = n.relationanchorpoint
     INNER JOIN neos_contentgraph_node p
        ON p.relationanchorpoint = h.parentnodeanchor
     INNER JOIN neos_contentgraph_hierarchyrelation ph
        on ph.childnodeanchor = p.relationanchorpoint
     WHERE
        p.nodeaggregateidentifier in (:entryNodeAggregateIdentifiers)
        AND h.contentstreamidentifier = :contentStreamIdentifier
		AND h.dimensionspacepointhash = :dimensionSpacePointHash
		AND ph.contentstreamidentifier = :contentStreamIdentifier
		AND ph.dimensionspacepointhash = :dimensionSpacePointHash
		###VISIBILITY_CONSTRAINTS_INITIAL###
union
     -- --------------------------------
     -- RECURSIVE query: do one "child" query step, taking into account the depth and node type constraints
     -- --------------------------------
     select
        c.*,
        h.contentstreamidentifier,
        h.name,

     	p.nodeaggregateidentifier as parentNodeAggregateIdentifier,
     	p.level + 1 as level,
     	h.position
     from
        tree p
	 inner join neos_contentgraph_hierarchyrelation h
        on h.parentnodeanchor = p.relationanchorpoint
	 inner join neos_contentgraph_node c
	    on h.childnodeanchor = c.relationanchorpoint
	 where
	 	h.contentstreamidentifier = :contentStreamIdentifier
		AND h.dimensionspacepointhash = :dimensionSpacePointHash
        ###VISIBILITY_CONSTRAINTS_RECURSION###

   -- select relationanchorpoint from neos_contentgraph_node
)
select * from tree
where
    1=1
    ###NODE_TYPE_CONSTRAINTS###
    ###SEARCH_TERM_CONSTRAINTS###
order by level asc, position asc;')
            ->parameter('entryNodeAggregateIdentifiers', array_map(function (NodeAggregateIdentifier $nodeAggregateIdentifier) {
                return (string)$nodeAggregateIdentifier;
            }, $entryNodeAggregateIdentifiers), Connection::PARAM_STR_ARRAY)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());

        self::addNodeTypeConstraintsToQuery($query, $nodeTypeConstraints, '###NODE_TYPE_CONSTRAINTS###', '');
        self::addSearchTermConstraintsToQuery($query, $searchTerm, '###SEARCH_TERM_CONSTRAINTS###', '');
        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'n', 'h', '###VISIBILITY_CONSTRAINTS_INITIAL###');
        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'c', 'h', '###VISIBILITY_CONSTRAINTS_RECURSION###');

        $result = [];
        foreach ($query->execute($this->getDatabaseConnection())->fetchAll() as $nodeRecord) {
            $result[] = $this->nodeFactory->mapNodeRowToNode($nodeRecord);
        }

        return $result;
    }

    /**
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function countNodes(): int
    {
        $query = new SqlQueryBuilder();
        $query->addToQuery('
SELECT COUNT(*) FROM neos_contentgraph_node n
 JOIN neos_contentgraph_hierarchyrelation h ON h.childnodeanchor = n.relationanchorpoint
 WHERE h.contentstreamidentifier = :contentStreamIdentifier
 AND h.dimensionspacepointhash = :dimensionSpacePointHash')
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());

        return (int)$query->execute($this->getDatabaseConnection())->fetch()['COUNT(*)'];
    }

    public function getInMemoryCache(): InMemoryCache
    {
        return $this->inMemoryCache;
    }
}
