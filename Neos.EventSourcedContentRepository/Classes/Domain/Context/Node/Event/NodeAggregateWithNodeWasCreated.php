<?php

namespace Neos\EventSourcedContentRepository\Domain\Context\Node\Event;

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
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeName;
use Neos\ContentRepository\Domain\ValueObject\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValue;
use Neos\EventSourcing\Event\EventInterface;

/**
 * Node aggregate with node was created event.
 */
final class NodeAggregateWithNodeWasCreated implements EventInterface, CopyableAcrossContentStreamsInterface
{
    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifier;

    /**
     * @var NodeTypeName
     */
    private $nodeTypeName;

    /**
     * Location of the node in the dimension space.
     *
     * @var DimensionSpacePoint
     */
    private $dimensionSpacePoint;

    /**
     * Visibility of node in the dimension space.
     *
     * @var DimensionSpacePointSet
     */
    private $visibleDimensionSpacePoints;

    /**
     * @var NodeIdentifier
     */
    private $nodeIdentifier;

    /**
     * @var NodeIdentifier
     */
    private $parentNodeIdentifier;

    /**
     * @var NodeName
     */
    private $nodeName;

    /**
     * (property name => PropertyValue).
     *
     * @var array<PropertyValue>
     */
    private $propertyDefaultValuesAndTypes;

    /**
     * NodeAggregateWithNodeWasCreated constructor.
     *
     * @param ContentStreamIdentifier                                                    $contentStreamIdentifier
     * @param NodeAggregateIdentifier                                                    $nodeAggregateIdentifier
     * @param NodeTypeName                                                               $nodeTypeName
     * @param DimensionSpacePoint                                                        $dimensionSpacePoint
     * @param DimensionSpacePointSet                                                     $visibleDimensionSpacePoints
     * @param NodeIdentifier                                                             $nodeIdentifier
     * @param NodeIdentifier                                                             $parentNodeIdentifier
     * @param NodeName                                                                   $nodeName
     * @param array<Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValue> $propertyDefaultValuesAndTypes
     */
    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeName $nodeTypeName,
        DimensionSpacePoint $dimensionSpacePoint,
        DimensionSpacePointSet $visibleDimensionSpacePoints,
        NodeIdentifier $nodeIdentifier,
        NodeIdentifier $parentNodeIdentifier,
        NodeName $nodeName,
        array $propertyDefaultValuesAndTypes
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->nodeTypeName = $nodeTypeName;
        $this->dimensionSpacePoint = $dimensionSpacePoint;
        $this->visibleDimensionSpacePoints = $visibleDimensionSpacePoints;
        $this->nodeIdentifier = $nodeIdentifier;
        $this->parentNodeIdentifier = $parentNodeIdentifier;
        $this->nodeName = $nodeName;
        $this->propertyDefaultValuesAndTypes = $propertyDefaultValuesAndTypes;
        foreach ($propertyDefaultValuesAndTypes as $propertyName => $property) {
            if (!$property instanceof PropertyValue) {
                throw new \InvalidArgumentException(sprintf('Property %s was not of type PropertyValue', $propertyName));
            }
        }
    }

    /**
     * @return ContentStreamIdentifier
     */
    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    /**
     * @return NodeAggregateIdentifier
     */
    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    /**
     * @return NodeTypeName
     */
    public function getNodeTypeName(): NodeTypeName
    {
        return $this->nodeTypeName;
    }

    /**
     * @return DimensionSpacePoint
     */
    public function getDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->dimensionSpacePoint;
    }

    /**
     * @return DimensionSpacePointSet
     */
    public function getVisibleDimensionSpacePoints(): DimensionSpacePointSet
    {
        return $this->visibleDimensionSpacePoints;
    }

    /**
     * @return NodeIdentifier
     */
    public function getNodeIdentifier(): NodeIdentifier
    {
        return $this->nodeIdentifier;
    }

    /**
     * @return NodeIdentifier
     */
    public function getParentNodeIdentifier(): NodeIdentifier
    {
        return $this->parentNodeIdentifier;
    }

    /**
     * @return NodeName
     */
    public function getNodeName(): NodeName
    {
        return $this->nodeName;
    }

    /**
     * @return array
     */
    public function getPropertyDefaultValuesAndTypes(): array
    {
        return $this->propertyDefaultValuesAndTypes;
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStream)
    {
        return new self(
            $targetContentStream,
            $this->nodeAggregateIdentifier,
            $this->nodeTypeName,
            $this->dimensionSpacePoint,
            $this->visibleDimensionSpacePoints,
            $this->nodeIdentifier,
            $this->parentNodeIdentifier,
            $this->nodeName,
            $this->propertyDefaultValuesAndTypes
        );
    }
}
