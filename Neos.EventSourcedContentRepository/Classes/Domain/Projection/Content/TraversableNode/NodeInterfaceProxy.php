<?php

namespace Neos\EventSourcedContentRepository\Domain\Projection\Content\TraversableNode;

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
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeName;
use Neos\ContentRepository\Domain\ValueObject\NodeTypeName;
use Neos\ContentRepository\Domain\ValueObject\PropertyCollectionInterface;

/**
 * This class is purely for code organization of TraversableNode.
 *
 * @internal
 */
trait NodeInterfaceProxy
{
    /**
     * @var NodeInterface
     */
    protected $node;

    public function getCacheEntryIdentifier():string
    {
        return $this->node->getCacheEntryIdentifier();
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->node->getContentStreamIdentifier();
    }

    public function getNodeIdentifier(): NodeIdentifier
    {
        return $this->node->getNodeIdentifier();
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->node->getNodeAggregateIdentifier();
    }

    public function getNodeTypeName(): NodeTypeName
    {
        return $this->node->getNodeTypeName();
    }

    public function getNodeType(): NodeType
    {
        return $this->node->getNodeType();
    }

    public function getNodeName(): NodeName
    {
        return $this->node->getNodeName();
    }

    public function getDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->node->getDimensionSpacePoint();
    }

    public function getProperties(): PropertyCollectionInterface
    {
        return $this->node->getProperties();
    }

    public function hasProperty($propertyName): bool
    {
        return $this->node->hasProperty($propertyName);
    }

    public function getProperty($propertyName)
    {
        return $this->node->getProperty($propertyName);
    }

    public function isHidden()
    {
        return $this->node->isHidden();
    }

    public function getLabel(): string
    {
        return $this->node->getLabel();
    }
}
