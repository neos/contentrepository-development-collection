<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\AffectedCoveredDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\AffectedOccupiedDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasRemoved;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Traits\LoadNodeTypeTrait;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Traits\RemoveNodeAggregateTrait;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Dto\StructureAdjustment;
use Neos\EventSourcing\EventStore\EventStore;
use Ramsey\Uuid\Uuid;

/**
 * @Flow\Scope("singleton")
 */
class DisallowedChildNodeAdjustment
{
    use RemoveNodeAggregateTrait;
    use LoadNodeTypeTrait;

    protected EventStore $eventStore;
    protected ProjectedNodeIterator $projectedNodeIterator;
    protected NodeTypeManager $nodeTypeManager;
    protected ContentGraphInterface $contentGraph;
    protected ReadSideMemoryCacheManager $readSideMemoryCacheManager;

    public function __construct(EventStore $eventStore, ProjectedNodeIterator $projectedNodeIterator, NodeTypeManager $nodeTypeManager, ContentGraphInterface $contentGraph, ReadSideMemoryCacheManager $readSideMemoryCacheManager)
    {
        $this->eventStore = $eventStore;
        $this->projectedNodeIterator = $projectedNodeIterator;
        $this->nodeTypeManager = $nodeTypeManager;
        $this->contentGraph = $contentGraph;
        $this->readSideMemoryCacheManager = $readSideMemoryCacheManager;
    }

    public function findAdjustmentsForNodeType(NodeTypeName $nodeTypeName): \Generator
    {
        $nodeType = $this->loadNodeType($nodeTypeName);

        if ($nodeType === null) {
            // no adjustments for unknown node types
            return;
        }

        foreach ($this->projectedNodeIterator->nodeAggregatesOfType($nodeTypeName) as $nodeAggregate) {
            $nodeType = $this->loadNodeType($nodeAggregate->getNodeTypeName());
            if ($nodeType === null) {
                // unknown child node type, so we skip this test as we won't be able to find out node type constraints
                continue;
            }

            // Here, we iterate over the covered dimension space points of the node aggregate one by one; as it can happen that the constraint
            // is only violated in e.g. "AT", but not in "DE". Then, we only want to remove the single edge.
            foreach ($nodeAggregate->getCoveredDimensionSpacePoints() as $coveredDimensionSpacePoint) {
                $subgraph = $this->contentGraph->getSubgraphByIdentifier($nodeAggregate->getContentStreamIdentifier(), $coveredDimensionSpacePoint, VisibilityConstraints::withoutRestrictions());

                $parentNode = $subgraph->findParentNode($nodeAggregate->getIdentifier());
                $grandparentNode = $parentNode !== null ? $subgraph->findParentNode($parentNode->getNodeAggregateIdentifier()) : null;


                $allowedByParent = true;
                $parentNodeType = null;
                if ($parentNode !== null) {
                    $parentNodeType = $this->loadNodeType($parentNode->getNodeTypeName());
                    if ($parentNodeType !== null) {
                        $allowedByParent = $parentNodeType->allowsChildNodeType($nodeType);
                    }
                }

                $allowedByGrandparent = false;
                $grandparentNodeType = null;
                if ($grandparentNode != null && $parentNode->isTethered()) {
                    $grandparentNodeType = $this->loadNodeType($grandparentNode->getNodeTypeName());
                    if ($grandparentNodeType !== null) {
                        $allowedByGrandparent = $grandparentNodeType->allowsGrandchildNodeType($parentNode->getNodeName()->jsonSerialize(), $nodeType);
                    }
                }

                if (!$allowedByParent && !$allowedByGrandparent) {
                    $node = $subgraph->findNodeByNodeAggregateIdentifier($nodeAggregate->getIdentifier());

                    $message = sprintf(
                        '
                        The parent node type "%s" is not allowing children of type "%s",
                        and the grandparent node type "%s" is not allowing grandchildren of type "%s".
                        Thus, the node is invalid at this location and should be removed.
                    ',
                        $parentNodeType !== null ? $parentNodeType->getName() : '',
                        $node->getNodeTypeName()->jsonSerialize(),
                        $grandparentNodeType !== null ? $grandparentNodeType->getName() : '',
                        $node->getNodeTypeName()->jsonSerialize(),
                    );

                    yield StructureAdjustment::createForNode($node, StructureAdjustment::DISALLOWED_CHILD_NODE, $message, function () use ($nodeAggregate, $coveredDimensionSpacePoint) {
                        $this->readSideMemoryCacheManager->disableCache();
                        return $this->removeNodeInSingleDimensionSpacePoint($nodeAggregate, $coveredDimensionSpacePoint);
                    });
                }
            }
        }
    }

    protected function getEventStore(): EventStore
    {
        return $this->eventStore;
    }

    private function removeNodeInSingleDimensionSpacePoint(ReadableNodeAggregateInterface $nodeAggregate, DimensionSpacePoint $dimensionSpacePoint): CommandResult
    {
        $events = DomainEvents::withSingleEvent(
            DecoratedEvent::addIdentifier(
                new NodeAggregateWasRemoved(
                    $nodeAggregate->getContentStreamIdentifier(),
                    $nodeAggregate->getIdentifier(),
                    AffectedOccupiedDimensionSpacePointSet::onlyGivenVariant(
                        $nodeAggregate,
                        $dimensionSpacePoint
                    ),
                    AffectedCoveredDimensionSpacePointSet::onlyGivenVariant(
                        $dimensionSpacePoint
                    )
                ),
                Uuid::uuid4()->toString()
            )
        );

        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($nodeAggregate->getContentStreamIdentifier());
        $this->getEventStore()->commit($streamName->getEventStreamName(), $events);
        return CommandResult::fromPublishedEvents($events);
    }

    protected function getNodeTypeManager(): NodeTypeManager
    {
        return $this->nodeTypeManager;
    }
}
