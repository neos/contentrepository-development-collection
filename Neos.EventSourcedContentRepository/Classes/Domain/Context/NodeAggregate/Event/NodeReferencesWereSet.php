<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\CopyableAcrossContentStreamsInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;

/**
 * A named reference from source to destination node was created
 *
 * @Flow\Proxy(false)
 */
final class NodeReferencesWereSet implements DomainEventInterface, CopyableAcrossContentStreamsInterface
{
    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    private $sourceNodeAggregateIdentifier;

    /**
     * @var DimensionSpacePoint
     */
    private $sourceOriginDimensionSpacePoint;

    /**
     * @var NodeAggregateIdentifier[]
     */
    private $destinationNodeAggregateIdentifiers;

    /**
     * @var PropertyName
     */
    private $referenceName;

    /**
     * @var UserIdentifier
     */
    private $initiatingUserIdentifier;

    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $sourceNodeAggregateIdentifier,
        DimensionSpacePoint $sourceOriginDimensionSpacePoint,
        array $destinationNodeAggregateIdentifiers,
        PropertyName $referenceName,
        UserIdentifier $initiatingUserIdentifier
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->sourceNodeAggregateIdentifier = $sourceNodeAggregateIdentifier;
        $this->sourceOriginDimensionSpacePoint = $sourceOriginDimensionSpacePoint;
        $this->destinationNodeAggregateIdentifiers = $destinationNodeAggregateIdentifiers;
        $this->referenceName = $referenceName;
        $this->initiatingUserIdentifier = $initiatingUserIdentifier;
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
    public function getSourceNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->sourceNodeAggregateIdentifier;
    }

    /**
     * @return DimensionSpacePoint
     */
    public function getSourceOriginDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->sourceOriginDimensionSpacePoint;
    }

    /**
     * @return NodeAggregateIdentifier[]
     */
    public function getDestinationNodeAggregateIdentifiers(): array
    {
        return $this->destinationNodeAggregateIdentifiers;
    }

    /**
     * @return PropertyName
     */
    public function getReferenceName(): PropertyName
    {
        return $this->referenceName;
    }

    /**
     * @return UserIdentifier
     */
    public function getInitiatingUserIdentifier(): UserIdentifier
    {
        return $this->initiatingUserIdentifier;
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): NodeReferencesWereSet
    {
        return new NodeReferencesWereSet(
            $targetContentStreamIdentifier,
            $this->sourceNodeAggregateIdentifier,
            $this->sourceOriginDimensionSpacePoint,
            $this->destinationNodeAggregateIdentifiers,
            $this->referenceName,
            $this->initiatingUserIdentifier
        );
    }
}
