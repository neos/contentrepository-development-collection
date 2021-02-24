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
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodes;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraints;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\TraversableNode\NodeInterfaceProxy;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;

/**
 * See {@see TraversableNodeInterface} for explanation.
 */
final class TraversableNode implements TraversableNodeInterface, ProtectedContextAwareInterface
{
    use NodeInterfaceProxy;

    /**
     * @var ContentSubgraphInterface
     */
    protected $subgraph;

    public function __construct(NodeInterface $node, ContentSubgraphInterface $subgraph)
    {
        $this->node = $node;
        $this->subgraph = $subgraph;
    }

    public function findParentNode(): TraversableNodeInterface
    {
        $node = $this->subgraph->findParentNode($this->getNodeAggregateIdentifier());
        if ($node === null) {
            throw new NodeException('This node has no parent', 1542982973);
        }
        return $node;
    }

    public function findNamedChildNode(NodeName $nodeName): TraversableNodeInterface
    {
        $node = $this->subgraph->findChildNodeConnectedThroughEdgeName($this->getNodeAggregateIdentifier(), $nodeName);
        if ($node === null) {
            throw new NodeException(sprintf('Child node with name "%s" does not exist', $nodeName), 1542982917);
        }

        return $node;
    }

    /**
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return TraversableNodes
     */
    public function findChildNodes(NodeTypeConstraints $nodeTypeConstraints = null, int $limit = null, int $offset = null): TraversableNodes
    {
        return $this->subgraph->findChildNodes(
            $this->getNodeAggregateIdentifier(),
            $nodeTypeConstraints,
            $limit,
            $offset
        );
    }

    public function countChildNodes(NodeTypeConstraints $nodeTypeConstraints = null): int
    {
        return $this->subgraph->countChildNodes($this->getNodeAggregateIdentifier(), $nodeTypeConstraints);
    }

    public function findNodePath(): NodePath
    {
        return $this->subgraph->findNodePath($this->getNodeAggregateIdentifier());
    }

    public function findReferencingNodes(): TraversableNodes
    {
        return $this->subgraph->findReferencingNodes($this->getNodeAggregateIdentifier());
    }

    /**
     * Retrieves and returns nodes referencing this node by name from its subgraph.
     *
     * @param PropertyName $edgeName
     * @return TraversableNodes
     */
    public function findNamedReferencingNodes(PropertyName $edgeName): TraversableNodes
    {
        return $this->subgraph->findReferencingNodes($this->getNodeAggregateIdentifier(), $edgeName);
    }

    /**
     * Retrieves and returns all sibling nodes of this node from its subgraph.
     * If node type constraints are specified, only nodes of that type are returned.
     *
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return TraversableNodes
     */
    public function findSiblingNodes(
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): TraversableNodes {
        return $this->subgraph->findSiblings($this->getNodeAggregateIdentifier(), $nodeTypeConstraints, $limit, $offset);
    }

    /**
     * Retrieves and returns all preceding sibling nodes of this node from its subgraph.
     * If node type constraints are specified, only nodes of that type are returned.
     *
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return TraversableNodes
     */
    public function findPrecedingSiblingNodes(
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): TraversableNodes {
        return $this->subgraph->findPrecedingSiblings($this->getNodeAggregateIdentifier(), $nodeTypeConstraints, $limit, $offset);
    }

    /**
     * Retrieves and returns all succeeding sibling nodes of this node from its subgraph.
     * If node type constraints are specified, only nodes of that type are returned.
     *
     * @param NodeTypeConstraints|null $nodeTypeConstraints
     * @param int|null $limit
     * @param int|null $offset
     * @return TraversableNodes
     */
    public function findSucceedingSiblingNodes(
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): TraversableNodes {
        return $this->subgraph->findSucceedingSiblings($this->getNodeAggregateIdentifier(), $nodeTypeConstraints, $limit, $offset);
    }

    /**
     * Retrieves and returns all nodes referenced by this node from its subgraph.
     * If node type constraints are specified, only nodes of that type are returned.
     *
     * This is the FORWARD direction of reference traversal, i.e.
     * to find all nodes referenced by a `reference` or `references` property
     *
     * @return TraversableNodes
     */
    public function findReferencedNodes(): TraversableNodes
    {
        return $this->subgraph->findReferencedNodes($this->getNodeAggregateIdentifier());
    }

    /**
     * Retrieves and returns nodes referenced by this node by name from its subgraph.
     *
     * @param PropertyName $edgeName
     * @return TraversableNodes
     */
    public function findNamedReferencedNodes(PropertyName $edgeName): TraversableNodes
    {
        return $this->subgraph->findReferencedNodes($this->getNodeAggregateIdentifier(), $edgeName);
    }

    /**
     * Returns the DimensionSpacePoint the node was *requested in*, i.e. one of the DimensionSpacePoints
     * this node covers. If you need the DimensionSpacePoint where the node is actually at home,
     * see getOriginDimensionSpacePoint()
     *
     * @return DimensionSpacePoint
     */
    public function getDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->subgraph->getDimensionSpacePoint();
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }

    /**
     * We need to re-implement getLabel here; and can NOT delegate it to the underlying Node,
     * so that the user can work with FlowQuery operations (which only operate on TraversableNodes).
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->getNodeType()->getNodeLabelGenerator()->getLabel($this) ?? '';
    }

    /**
     * Compare whether two traversable nodes are equal
     *
     * @param TraversableNodeInterface $other
     * @return bool
     */
    public function equals(TraversableNodeInterface $other): bool
    {
        return $this->getContentStreamIdentifier()->equals($other->getContentStreamIdentifier())
            && $this->getDimensionSpacePoint()->equals($other->getDimensionSpacePoint())
            && $this->getNodeAggregateIdentifier()->equals($other->getNodeAggregateIdentifier());
    }
}
