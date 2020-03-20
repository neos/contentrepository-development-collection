<?php
declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository;

/*
 * This file is part of the Neos.ContentGraph.DoctrineDbalAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Doctrine\DBAL\Connection;
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
     * @var DbalClient
     */
    protected $client;

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

    /**
     * @param SqlQueryBuilder $query
     * @param NodeTypeConstraints $nodeTypeConstraints
     * @param string|null $markerToReplaceInQuery
     * @param string $tableReference
     * @param string $concatenation
     */
    protected static function addNodeTypeConstraintsToQuery(
        SqlQueryBuilder $query,
        NodeTypeConstraints $nodeTypeConstraints = null,
        string $markerToReplaceInQuery = null,
        string $tableReference = 'c',
        string $concatenation = 'AND'
    ): void {
        if ($nodeTypeConstraints) {
            if (!empty($nodeTypeConstraints->getExplicitlyAllowedNodeTypeNames())) {
                $allowanceQueryPart = ($tableReference ? $tableReference . '.' : '') . 'nodetypename IN (:explicitlyAllowedNodeTypeNames)';
                $query->parameter('explicitlyAllowedNodeTypeNames', $nodeTypeConstraints->getExplicitlyAllowedNodeTypeNames(), Connection::PARAM_STR_ARRAY);
            } else {
                $allowanceQueryPart = '';
            }
            if (!empty($nodeTypeConstraints->getExplicitlyDisallowedNodeTypeNames())) {
                $disAllowanceQueryPart = ($tableReference ? $tableReference . '.' : '') . 'nodetypename NOT IN (:explicitlyDisallowedNodeTypeNames)';
                $query->parameter('explicitlyDisallowedNodeTypeNames', $nodeTypeConstraints->getExplicitlyDisallowedNodeTypeNames(), Connection::PARAM_STR_ARRAY);
            } else {
                $disAllowanceQueryPart = '';
            }

            if ($allowanceQueryPart && $disAllowanceQueryPart) {
                $query->addToQuery(' ' . $concatenation . ' (' . $allowanceQueryPart . ($nodeTypeConstraints->isWildcardAllowed() ? ' OR ' : ' AND ') . $disAllowanceQueryPart . ')', $markerToReplaceInQuery);
            } elseif ($allowanceQueryPart && !$nodeTypeConstraints->isWildcardAllowed()) {
                $query->addToQuery(' ' . $concatenation . ' ' . $allowanceQueryPart, $markerToReplaceInQuery);
            } elseif ($disAllowanceQueryPart) {
                $query->addToQuery(' ' . $concatenation . ' ' . $disAllowanceQueryPart, $markerToReplaceInQuery);
            } else {
                $query->addToQuery('', $markerToReplaceInQuery);
            }
        }
    }

    protected static function addSearchTermConstraintsToQuery(
        SqlQueryBuilder $query,
        ?ContentRepository\Projection\Content\SearchTerm $searchTerm,
        string $markerToReplaceInQuery = null,
        string $tableReference = 'c',
        string $concatenation = 'AND'
    ): void {
        if ($searchTerm) {
            // Magic copied from legacy NodeSearchService.

            // Convert to lowercase, then to json, and then trim quotes from json to have valid JSON escaping.
            $likeParameter = '%' . trim(json_encode(UnicodeFunctions::strtolower($searchTerm->getTerm()), JSON_UNESCAPED_UNICODE), '"') . '%';

            $query
                ->addToQuery($concatenation . ' LOWER(' . ($tableReference ? $tableReference . '.' : '') . 'properties) LIKE :term', $markerToReplaceInQuery)
                ->parameter('term', $likeParameter);
        } else {
            $query->addToQuery('', $markerToReplaceInQuery);
        };
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
    ): array {
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
            $query = new SqlQueryBuilder();
            $query->addToQuery('
-- ContentSubgraph::findNodeByNodeAggregateIdentifier
SELECT n.*, h.name, h.contentstreamidentifier FROM neos_contentgraph_node n
 INNER JOIN neos_contentgraph_hierarchyrelation h ON h.childnodeanchor = n.relationanchorpoint

 WHERE n.nodeaggregateidentifier = :nodeAggregateIdentifier
 AND h.contentstreamidentifier = :contentStreamIdentifier
 AND h.dimensionspacepointhash = :dimensionSpacePointHash
 ')
                ->parameter('nodeAggregateIdentifier', (string)$nodeAggregateIdentifier)
                ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
                ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());

            $query = self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints);

            $nodeRow = $query->execute($this->getDatabaseConnection())->fetch();
            if ($nodeRow === false) {
                $cache->rememberNonExistingNodeAggregateIdentifier($nodeAggregateIdentifier);
                return null;
            }

            $node = $this->nodeFactory->mapNodeRowToNode($nodeRow);
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
    ): int {
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

        $query = new SqlQueryBuilder();
        $query->addToQuery(
            '
-- ContentSubgraph::findParentNode
SELECT p.*, h.contentstreamidentifier, hp.name FROM neos_contentgraph_node p
 INNER JOIN neos_contentgraph_hierarchyrelation h ON h.parentnodeanchor = p.relationanchorpoint
 INNER JOIN neos_contentgraph_node c ON h.childnodeanchor = c.relationanchorpoint
 INNER JOIN neos_contentgraph_hierarchyrelation hp ON hp.childnodeanchor = p.relationanchorpoint
 WHERE c.nodeaggregateidentifier = :childNodeAggregateIdentifier
 AND h.contentstreamidentifier = :contentStreamIdentifier
 AND hp.contentstreamidentifier = :contentStreamIdentifier
 AND h.dimensionspacepointhash = :dimensionSpacePointHash
 AND hp.dimensionspacepointhash = :dimensionSpacePointHash'
        )
            ->parameter('childNodeAggregateIdentifier', (string)$childNodeAggregateIdentifier)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash());

        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'p');

        $nodeRow = $query->execute($this->getDatabaseConnection())->fetch();

        $node = $nodeRow ? $this->nodeFactory->mapNodeRowToNode($nodeRow) : null;
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
    ): ?NodeInterface {
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
    ): array {
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
    ): array {
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
    ): SubtreeInterface {
        $query = new SqlQueryBuilder();
        $query->addToQuery('
-- ContentSubgraph::findSubtrees

-- we build a set of recursive trees, ready to be rendered e.g. in a menu. Because the menu supports starting at multiple nodes, we also support starting at multiple nodes at once.
with recursive tree as (
     -- --------------------------------
     -- INITIAL query: select the root nodes of the tree; as given in $menuLevelNodeIdentifiers
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
     inner join neos_contentgraph_hierarchyrelation h
        on h.childnodeanchor = n.relationanchorpoint
     where
        n.nodeaggregateidentifier in (:entryNodeAggregateIdentifiers)
        and h.contentstreamidentifier = :contentStreamIdentifier
		AND h.dimensionspacepointhash = :dimensionSpacePointHash
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
		and p.level + 1 <= :maximumLevels
        ###NODE_TYPE_CONSTRAINTS###
        ###VISIBILITY_CONSTRAINTS_RECURSION###

   -- select relationanchorpoint from neos_contentgraph_node
)
select * from tree
order by level asc, position asc;')
            ->parameter('entryNodeAggregateIdentifiers', array_map(function (NodeAggregateIdentifier $nodeAggregateIdentifier) {
                return (string)$nodeAggregateIdentifier;
            }, $entryNodeAggregateIdentifiers), Connection::PARAM_STR_ARRAY)
            ->parameter('contentStreamIdentifier', (string)$this->getContentStreamIdentifier())
            ->parameter('dimensionSpacePointHash', $this->getDimensionSpacePoint()->getHash())
            ->parameter('maximumLevels', $maximumLevels);

        self::addNodeTypeConstraintsToQuery($query, $nodeTypeConstraints, '###NODE_TYPE_CONSTRAINTS###');

        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'n', 'h', '###VISIBILITY_CONSTRAINTS_INITIAL###');
        self::addRestrictionRelationConstraintsToQuery($query, $this->visibilityConstraints, 'c', 'h', '###VISIBILITY_CONSTRAINTS_RECURSION###');

        $result = $query->execute($this->getDatabaseConnection())->fetchAll();

        $subtreesByNodeIdentifier = [];
        $subtreesByNodeIdentifier['ROOT'] = new Subtree(0);

        $nodePathCache = $this->getInMemoryCache()->getNodePathCache();

        foreach ($result as $nodeData) {
            $node = $this->nodeFactory->mapNodeRowToNode($nodeData);
            $this->getInMemoryCache()->getNodeByNodeAggregateIdentifierCache()->add($node->getNodeAggregateIdentifier(), $node);

            if (!isset($subtreesByNodeIdentifier[$nodeData['parentNodeAggregateIdentifier']])) {
                throw new \Exception('TODO: must not happen: We expect the tree to be returned from the DB in-order; so the parents must have been returned before the children.');
            }

            // SECTION: pre-fill nodePathCache
            if ($nodeData['parentNodeAggregateIdentifier'] === 'ROOT') {
                // we have the root of the subtree. So we need to fetch the node path of that node. The following line
                // properly fills the node path cache as a side-effect; which we need for performance lateron.
                $this->findNodePath($node->getNodeAggregateIdentifier());
            } else {
                // we are not the root nodes; but deeper down in the hierarchy.
                $parentNodeAggregateIdentifier = NodeAggregateIdentifier::fromString($nodeData['parentNodeAggregateIdentifier']);
                $parentNodePath = $nodePathCache->get($parentNodeAggregateIdentifier);

                if ($parentNodePath === null) {
                    throw new \RuntimeException('TODO: parent node path not filled in cache. SHOULD NEVER HAPPEN!!!!');
                }
                $nodePath = $parentNodePath->appendPathSegment($node->getNodeName());
                $nodePathCache->add($node->getNodeAggregateIdentifier(), $nodePath);
            }


            // TODO: namedChildNodeByNodeIdentifierCache

            $subtree = new Subtree($nodeData['level'], $node);
            $subtreesByNodeIdentifier[$nodeData['parentNodeAggregateIdentifier']]->add($subtree);
            $subtreesByNodeIdentifier[$nodeData['nodeaggregateidentifier']] = $subtree;

            // also add the parents to the child -> parent cache.
            /* @var $parentSubtree Subtree */
            $parentSubtree = $subtreesByNodeIdentifier[$nodeData['parentNodeAggregateIdentifier']];
            if ($parentSubtree->getNode() !== null) {
                $this->getInMemoryCache()->getParentNodeIdentifierByChildNodeIdentifierCache()->add($node->getNodeAggregateIdentifier(), $parentSubtree->getNode()->getNodeAggregateIdentifier());
            }
        }

        // SECTION: pre-fill allChildNodesByNodeIdentifierCache
        self::prefillAllChildNodesByNodeIdentifierCache($this->getInMemoryCache()->getAllChildNodesByNodeIdentifierCache(), $subtreesByNodeIdentifier['ROOT'], $maximumLevels, $nodeTypeConstraints);

        return $subtreesByNodeIdentifier['ROOT'];
    }

    protected function prefillAllChildNodesByNodeIdentifierCache(InMemoryCache\AllChildNodesByNodeIdentifierCache $allChildNodesByNodeIdentifierCache, SubtreeInterface $subtree, int $maximumLevels, NodeTypeConstraints $nodeTypeConstraints)
    {
        if ($subtree->getLevel() >= $maximumLevels) {
            // at this point, the subtree is going as deep as the maximum levels say; thus we cannot add the last level to the AllChildNodesByNodeIdentifierCache;
            // because we do not know if the nodes at maximumLevel have further children
            return;
        }

        $childNodes = [];
        foreach ($subtree->getChildren() as $childSubtree) {
            $childNodes[] = $childSubtree->getNode();
            self::prefillAllChildNodesByNodeIdentifierCache($allChildNodesByNodeIdentifierCache, $childSubtree, $maximumLevels, $nodeTypeConstraints);
        }

        if ($subtree->getNode()) {
            // the root node of the subtree does not have a node; thus we need this if condition here.
            $allChildNodesByNodeIdentifierCache->add($subtree->getNode()->getNodeAggregateIdentifier(), $nodeTypeConstraints, $childNodes);
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
