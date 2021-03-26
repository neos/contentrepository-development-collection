<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment;

use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\Dto\PropertyValuesToWrite;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasMoved;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature\TetheredNodeInternals;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeVariantAssignment;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeVariantAssignments;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\PropertyConversionService;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Traits\LoadNodeTypeTrait;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Traits\RemoveNodeAggregateTrait;
use Neos\EventSourcedContentRepository\Domain\ValueObject\NodeMoveMapping;
use Neos\EventSourcedContentRepository\Domain\ValueObject\NodeMoveMappings;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\DimensionSpace\DimensionSpace;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature\NodeVariationInternals;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Dto\StructureAdjustment;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventStore;
use Ramsey\Uuid\Uuid;

/**
 * @Flow\Scope("singleton")
 */
class TetheredNodeAdjustments
{
    use NodeVariationInternals;
    use RemoveNodeAggregateTrait;
    use LoadNodeTypeTrait;
    use TetheredNodeInternals;

    protected EventStore $eventStore;
    protected ProjectedNodeIterator $projectedNodeIterator;
    protected NodeTypeManager $nodeTypeManager;
    protected DimensionSpace\InterDimensionalVariationGraph  $interDimensionalVariationGraph;
    protected ContentGraphInterface $contentGraph;
    protected PropertyConversionService $propertyConversionService;
    protected ReadSideMemoryCacheManager $readSideMemoryCacheManager;

    public function __construct(EventStore $eventStore, ProjectedNodeIterator $projectedNodeIterator, NodeTypeManager $nodeTypeManager, DimensionSpace\InterDimensionalVariationGraph $interDimensionalVariationGraph, ContentGraphInterface $contentGraph, PropertyConversionService $propertyConversionService, ReadSideMemoryCacheManager $readSideMemoryCacheManager)
    {
        $this->eventStore = $eventStore;
        $this->projectedNodeIterator = $projectedNodeIterator;
        $this->nodeTypeManager = $nodeTypeManager;
        $this->interDimensionalVariationGraph = $interDimensionalVariationGraph;
        $this->contentGraph = $contentGraph;
        $this->propertyConversionService = $propertyConversionService;
        $this->readSideMemoryCacheManager = $readSideMemoryCacheManager;
    }

    public function findAdjustmentsForNodeType(NodeTypeName $nodeTypeName): \Generator
    {
        $nodeType = $this->loadNodeType($nodeTypeName);
        if ($nodeType === null) {
            // In case we cannot find the expected tethered nodes, this fix cannot do anything.
            return;
        }
        $expectedTetheredNodes = $nodeType->getAutoCreatedChildNodes();

        foreach ($this->projectedNodeIterator->nodeAggregatesOfType($nodeTypeName) as $nodeAggregate) {
            // find missing tethered nodes
            $foundMissingOrDisallowedTetheredNodes = false;
            foreach ($nodeAggregate->getNodes() as $node) {
                foreach ($expectedTetheredNodes as $tetheredNodeName => $expectedTetheredNodeType) {
                    $tetheredNodeName = NodeName::fromString($tetheredNodeName);

                    $subgraph = $this->contentGraph->getSubgraphByIdentifier($node->getContentStreamIdentifier(), $node->getOriginDimensionSpacePoint(), VisibilityConstraints::withoutRestrictions());
                    $tetheredNode = $subgraph->findChildNodeConnectedThroughEdgeName($node->getNodeAggregateIdentifier(), $tetheredNodeName);
                    if ($tetheredNode === null) {
                        $foundMissingOrDisallowedTetheredNodes = true;
                        // $nestedNode not found - so a tethered node is missing in the OriginDimensionSpacePoint of the $node
                        yield StructureAdjustment::createForNode($node, StructureAdjustment::TETHERED_NODE_MISSING, 'The tethered child node "' . $tetheredNodeName . '" is missing.', function () use ($nodeAggregate, $node, $tetheredNodeName, $expectedTetheredNodeType) {
                            $this->readSideMemoryCacheManager->disableCache();

                            $events = $this->createEventsForMissingTetheredNode($nodeAggregate, $node, $tetheredNodeName, $expectedTetheredNodeType, UserIdentifier::forSystemUser());

                            $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($node->getContentStreamIdentifier());
                            $this->getEventStore()->commit($streamName->getEventStreamName(), $events);
                            return CommandResult::fromPublishedEvents($events);
                        });
                    } else {
                        yield from $this->ensureNodeIsTethered($tetheredNode);
                        yield from $this->ensureNodeIsOfType($tetheredNode, $expectedTetheredNodeType);
                    }
                }
            }

            // find disallowed tethered nodes
            $tetheredNodeAggregates = $this->contentGraph->findTetheredChildNodeAggregates($nodeAggregate->getContentStreamIdentifier(), $nodeAggregate->getIdentifier());
            foreach ($tetheredNodeAggregates as $tetheredNodeAggregate) {
                if (!isset($expectedTetheredNodes[(string)$tetheredNodeAggregate->getNodeName()])) {
                    $foundMissingOrDisallowedTetheredNodes = true;
                    yield StructureAdjustment::createForNodeAggregate($tetheredNodeAggregate, StructureAdjustment::DISALLOWED_TETHERED_NODE, 'The tethered child node "' . $tetheredNodeAggregate->getNodeName()->jsonSerialize() . '" should be removed.', function () use ($tetheredNodeAggregate) {
                        $this->readSideMemoryCacheManager->disableCache();
                        return $this->removeNodeAggregate($tetheredNodeAggregate);
                    });
                }
            }

            // find wrongly ordered tethered nodes
            if ($foundMissingOrDisallowedTetheredNodes === false) {
                foreach ($nodeAggregate->getNodes() as $node) {
                    $subgraph = $this->contentGraph->getSubgraphByIdentifier($node->getContentStreamIdentifier(), $node->getOriginDimensionSpacePoint(), VisibilityConstraints::withoutRestrictions());
                    $childNodes = $subgraph->findChildNodes($node->getNodeAggregateIdentifier());

                    /** is indexed by node name, and the value is the tethered node itself */
                    $actualTetheredChildNodes = [];
                    foreach ($childNodes as $childNode) {
                        if ($childNode->isTethered()) {
                            $actualTetheredChildNodes[(string)$childNode->getNodeName()] = $childNode;
                        }
                    }

                    if (array_keys($actualTetheredChildNodes) !== array_keys($expectedTetheredNodes)) {
                        // we need to re-order: We go from the last to the first
                        yield StructureAdjustment::createForNode($node, StructureAdjustment::TETHERED_NODE_WRONGLY_ORDERED, 'Tethered nodes wrongly ordered, expected: ' . implode(', ', array_keys($expectedTetheredNodes)) . ' - actual: ' . implode(', ', array_keys($actualTetheredChildNodes)), function () use ($node, $actualTetheredChildNodes, $expectedTetheredNodes) {
                            $this->readSideMemoryCacheManager->disableCache();
                            return $this->reorderNodes($node->getContentStreamIdentifier(), $actualTetheredChildNodes, array_keys($expectedTetheredNodes));
                        });
                    }
                }
            }
        }
    }

    private function ensureNodeIsTethered(NodeInterface $node): \Generator
    {
        if (!$node->isTethered()) {
            yield StructureAdjustment::createForNode($node, StructureAdjustment::NODE_IS_NOT_TETHERED_BUT_SHOULD_BE, 'This node should be a tethered node, but is not.');
        }
    }

    private function ensureNodeIsOfType(NodeInterface $node, NodeType $expectedNodeType): \Generator
    {
        if ($node->getNodeTypeName()->getValue() !== $expectedNodeType->getName()) {
            yield StructureAdjustment::createForNode($node, StructureAdjustment::TETHERED_NODE_TYPE_WRONG, 'should be of type "' . $expectedNodeType . '", but was "' . $node->getNodeTypeName()->getValue() . '".');
        }
    }

    protected function getInterDimensionalVariationGraph(): DimensionSpace\InterDimensionalVariationGraph
    {
        return $this->interDimensionalVariationGraph;
    }

    protected function getContentGraph(): ContentGraphInterface
    {
        return $this->contentGraph;
    }


    private function getDefaultPropertyValues(NodeType $nodeType): SerializedPropertyValues
    {
        $rawDefaultPropertyValues = PropertyValuesToWrite::fromArray($nodeType->getDefaultValuesForProperties());
        return $this->propertyConversionService->serializePropertyValues($rawDefaultPropertyValues, $nodeType);
    }

    protected function getEventStore(): EventStore
    {
        return $this->eventStore;
    }

    protected function getNodeTypeManager(): NodeTypeManager
    {
        return $this->nodeTypeManager;
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param array $actualTetheredChildNodes array key: name of tethered child node. Value: the Node itself.
     * @param array $expectedNodeOrdering an array depicting the expected tethered order, like ["node1", "node2"]
     * @return CommandResult
     */
    private function reorderNodes(ContentStreamIdentifier $contentStreamIdentifier, array $actualTetheredChildNodes, array $expectedNodeOrdering): CommandResult
    {
        $events = DomainEvents::createEmpty();

        // we move from back to front through the expected ordering; as we always specify the **succeeding** sibling.
        $succeedingSiblingNodeName = array_pop($expectedNodeOrdering);
        while ($nodeNameToMove = array_pop($expectedNodeOrdering)) {
            // let's move $nodeToMove before $succeedingNode.
            /* @var $nodeToMove NodeInterface */
            $nodeToMove = $actualTetheredChildNodes[$nodeNameToMove];
            /* @var $succeedingNode NodeInterface */
            $succeedingNode = $actualTetheredChildNodes[$succeedingSiblingNodeName];

            $events = $events->appendEvent(
                DecoratedEvent::addIdentifier(
                    new NodeAggregateWasMoved(
                        $contentStreamIdentifier,
                        $nodeToMove->getNodeAggregateIdentifier(),
                        NodeMoveMappings::fromArray([
                            new NodeMoveMapping(
                                $nodeToMove->getOriginDimensionSpacePoint(),
                                NodeVariantAssignments::createFromArray([]), // we do not want to assign new parents
                                NodeVariantAssignments::createFromArray([
                                    $nodeToMove->getOriginDimensionSpacePoint()->getHash() => new NodeVariantAssignment(
                                        $succeedingNode->getNodeAggregateIdentifier(),
                                        $succeedingNode->getOriginDimensionSpacePoint()
                                    )
                                ])
                            )
                        ]),
                        new DimensionSpace\DimensionSpacePointSet([]),
                        UserIdentifier::forSystemUser()
                    ),
                    Uuid::uuid4()->toString()
                )
            );

            // now, go one step left.
            $succeedingSiblingNodeName = $nodeNameToMove;
        }

        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($contentStreamIdentifier);
        $this->eventStore->commit($streamName->getEventStreamName(), $events);
        return CommandResult::fromPublishedEvents($events);
    }
}
