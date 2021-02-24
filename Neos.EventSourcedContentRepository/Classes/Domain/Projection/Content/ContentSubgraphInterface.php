<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Projection\Content;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraints;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodes;
use Neos\EventSourcedContentRepository\Domain\Context\ContentSubgraph\SubtreeInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;

/**
 * The interface to be implemented by content subgraphs
 */
interface ContentSubgraphInterface extends \JsonSerializable
{
    public function findChildNodes(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): TraversableNodes;

    public function findReferencedNodes(
        NodeAggregateIdentifier $nodeAggregateAggregateIdentifier,
        PropertyName $name = null
    ): TraversableNodes;

    public function findReferencingNodes(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        PropertyName $name = null
    ): TraversableNodes;

    public function findNodeByNodeAggregateIdentifier(NodeAggregateIdentifier $nodeAggregateIdentifier): ?TraversableNodeInterface;

    public function countChildNodes(
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeTypeConstraints $nodeTypeConstraints = null
    ): int;

    public function findParentNode(NodeAggregateIdentifier $childAggregateIdentifier): ?TraversableNodeInterface;

    public function findNodeByPath(
        NodePath $path,
        NodeAggregateIdentifier $startingNodeAggregateIdentifier
    ): ?TraversableNodeInterface;

    public function findChildNodeConnectedThroughEdgeName(
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeName $edgeName
    ): ?TraversableNodeInterface;

    public function findSiblings(
        NodeAggregateIdentifier $sibling,
        ?NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): TraversableNodes;

    public function findSucceedingSiblings(
        NodeAggregateIdentifier $sibling,
        ?NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): TraversableNodes;

    public function findPrecedingSiblings(
        NodeAggregateIdentifier $sibling,
        ?NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null, int $offset = null
    ): TraversableNodes;

    public function findNodePath(NodeAggregateIdentifier $nodeAggregateIdentifier): NodePath;

    public function getContentStreamIdentifier(): ContentStreamIdentifier;

    public function getDimensionSpacePoint(): DimensionSpacePoint;

    /**
     * @param NodeAggregateIdentifier[] $entryNodeAggregateIdentifiers
     * @param int $maximumLevels
     * @param NodeTypeConstraints $nodeTypeConstraints
     * @return SubtreeInterface
     */
    public function findSubtrees(array $entryNodeAggregateIdentifiers, int $maximumLevels, NodeTypeConstraints $nodeTypeConstraints): SubtreeInterface;

    /**
     * Recursively find all nodes underneath the $entryNodeAggregateIdentifiers, which match the node type constraints specified by NodeTypeConstraints.
     *
     * If a Search Term is specified, the properties are searched for this search term.
     *
     * @param array $entryNodeAggregateIdentifiers
     * @param NodeTypeConstraints $nodeTypeConstraints
     * @param SearchTerm|null $searchTerm
     * @return TraversableNodes
     */
    public function findDescendants(array $entryNodeAggregateIdentifiers, NodeTypeConstraints $nodeTypeConstraints, ?SearchTerm $searchTerm): TraversableNodes;

    public function countNodes(): int;

    public function getInMemoryCache(): InMemoryCache;
}
