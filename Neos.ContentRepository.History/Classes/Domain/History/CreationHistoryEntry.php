<?php
declare(strict_types=1);

namespace Neos\ContentRepository\History\Domain\History;

/*
 * This file is part of the Neos.ContentRepository.History package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Projection\Content\PropertyCollectionInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\Flow\Annotations as Flow;

/**
 * The creation history entry read model
 */
final class CreationHistoryEntry implements HistoryEntryInterface
{
    /**
     * @var HistoryEntryIdentifier
     */
    private $identifier;

    /**
     * @var string
     */
    private $nodeAggregateLabel;

    /**
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifier;

    /**
     * @var AgentIdentifier
     */
    private $agentIdentifier;

    /**
     * @var \DateTimeImmutable
     */
    private $recordedAt;

    /**
     * @var NodeTypeName
     */
    private $nodeTypeName;

    /**
     * @var DimensionSpacePoint
     */
    private $originDimensionSpacePoint;

    /**
     * @var string
     */
    private $parentNodeAggregateLabel;

    /**
     * @var NodeAggregateIdentifier
     */
    private $parentNodeAggregateIdentifier;

    /**
     * @var NodeName
     */
    private $nodeName;

    /**
     * @var PropertyCollectionInterface
     */
    private $initialPropertyValues;

    public function __construct(
        HistoryEntryIdentifier $identifier,
        string $nodeAggregateLabel,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        AgentIdentifier $agentIdentifier,
        \DateTimeImmutable $recordedAt,
        NodeTypeName $nodeTypeName,
        DimensionSpacePoint $originDimensionSpacePoint,
        ?string $parentNodeAggregateLabel,
        ?NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        ?NodeName $nodeName,
        PropertyCollectionInterface $initialPropertyValues
    ) {
        $this->identifier = $identifier;
        $this->nodeAggregateLabel = $nodeAggregateLabel;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->agentIdentifier = $agentIdentifier;
        $this->recordedAt = $recordedAt;
        $this->nodeTypeName = $nodeTypeName;
        $this->originDimensionSpacePoint = $originDimensionSpacePoint;
        $this->parentNodeAggregateLabel = $parentNodeAggregateLabel;
        $this->parentNodeAggregateIdentifier = $parentNodeAggregateIdentifier;
        $this->nodeName = $nodeName;
        $this->initialPropertyValues = $initialPropertyValues;
    }

    /**
     * @return HistoryEntryIdentifier
     */
    public function getIdentifier(): HistoryEntryIdentifier
    {
        return $this->identifier;
    }

    public function getNodeAggregateLabel(): string
    {
        return $this->nodeAggregateLabel;
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    public function getAgentIdentifier(): AgentIdentifier
    {
        return $this->agentIdentifier;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getNodeTypeName(): NodeTypeName
    {
        return $this->nodeTypeName;
    }

    public function getOriginDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->originDimensionSpacePoint;
    }

    public function getParentNodeAggregateLabel(): ?string
    {
        return $this->parentNodeAggregateLabel;
    }

    public function getParentNodeAggregateIdentifier(): ?NodeAggregateIdentifier
    {
        return $this->parentNodeAggregateIdentifier;
    }

    public function getNodeName(): ?NodeName
    {
        return $this->nodeName;
    }

    public function getInitialPropertyValues(): PropertyCollectionInterface
    {
        return $this->initialPropertyValues;
    }
}
