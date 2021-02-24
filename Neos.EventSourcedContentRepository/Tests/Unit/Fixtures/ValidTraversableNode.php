<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Tests\Unit\Fixtures;

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
use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\Model\ArrayPropertyCollection;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraints;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Projection\Content\PropertyCollectionInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodes;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\Exception\NodeNotFoundException;

/**
 * A valid traversable node implementation
 */
class ValidTraversableNode implements TraversableNodeInterface
{
    public function getCacheEntryIdentifier(): string
    {
        return '';
    }

    public function isRoot(): bool
    {
        return false;
    }

    public function isTethered(): bool
    {
        return false;
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return ContentStreamIdentifier::create();
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return NodeAggregateIdentifier::create();
    }

    public function getNodeTypeName(): NodeTypeName
    {
        return NodeTypeName::fromString('Neos.ContentRepository:Test');
    }

    public function getNodeType(): NodeType
    {
        return new NodeType('Neos.ContentRepository:Test', [], []);
    }

    public function getNodeName(): ?NodeName
    {
        return null;
    }

    public function getOriginDimensionSpacePoint(): OriginDimensionSpacePoint
    {
        return OriginDimensionSpacePoint::fromArray([]);
    }

    public function getProperties(): PropertyCollectionInterface
    {
        return new ArrayPropertyCollection([]);
    }

    public function getProperty($propertyName)
    {
        return null;
    }

    public function hasProperty($propertyName): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return '';
    }

    public function getDimensionSpacePoint(): DimensionSpacePoint
    {
        return DimensionSpacePoint::fromArray([]);
    }

    public function findParentNode(): TraversableNodeInterface
    {
        throw new NodeNotFoundException();
    }

    public function findNodePath(): NodePath
    {
        return NodePath::fromString('/');
    }

    public function findNamedChildNode(NodeName $nodeName): TraversableNodeInterface
    {
        throw new NodeNotFoundException();
    }

    public function findChildNodes(
        NodeTypeConstraints $nodeTypeConstraints = null,
        int $limit = null,
        int $offset = null
    ): TraversableNodes {
        return TraversableNodes::fromArray([]);
    }

    public function countChildNodes(NodeTypeConstraints $nodeTypeConstraints = null): int
    {
        return 0;
    }

    public function findReferencedNodes(): TraversableNodes
    {
        return TraversableNodes::fromArray([]);
    }

    public function findNamedReferencedNodes(PropertyName $edgeName): TraversableNodes
    {
        return TraversableNodes::fromArray([]);
    }

    public function findReferencingNodes(): TraversableNodes
    {
        return TraversableNodes::fromArray([]);
    }

    public function findNamedReferencingNodes(PropertyName $nodeName): TraversableNodes
    {
        return TraversableNodes::fromArray([]);
    }

    public function equals(TraversableNodeInterface $other): bool
    {
        return false;
    }
}
