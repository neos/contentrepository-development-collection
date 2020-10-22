<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment;

use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Traits\LoadNodeTypeTrait;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Traits\RemoveNodeAggregateTrait;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Dto\StructureAdjustment;
use Neos\EventSourcing\EventStore\EventStore;

/**
 * @Flow\Scope("singleton")
 */
class UnknownNodeTypeAdjustment
{
    use RemoveNodeAggregateTrait;
    use LoadNodeTypeTrait;

    protected EventStore $eventStore;
    protected ProjectedNodeIterator $projectedNodeIterator;
    protected NodeTypeManager $nodeTypeManager;
    protected ReadSideMemoryCacheManager $readSideMemoryCacheManager;

    public function __construct(EventStore $eventStore, ProjectedNodeIterator $projectedNodeIterator, NodeTypeManager $nodeTypeManager, ReadSideMemoryCacheManager $readSideMemoryCacheManager)
    {
        $this->eventStore = $eventStore;
        $this->projectedNodeIterator = $projectedNodeIterator;
        $this->nodeTypeManager = $nodeTypeManager;
        $this->readSideMemoryCacheManager = $readSideMemoryCacheManager;
    }

    public function findAdjustmentsForNodeType(NodeTypeName $nodeTypeName): \Generator
    {
        $nodeType = $this->loadNodeType($nodeTypeName);
        if ($nodeType === null) {
            // node type is not existing right now.
            yield from $this->removeAllNodesOfType($nodeTypeName);
        }
    }

    protected function getEventStore(): EventStore
    {
        return $this->eventStore;
    }

    private function removeAllNodesOfType(NodeTypeName $nodeTypeName): \Generator
    {
        foreach ($this->projectedNodeIterator->nodeAggregatesOfType($nodeTypeName) as $nodeAggregate) {
            yield StructureAdjustment::createForNodeAggregate($nodeAggregate, StructureAdjustment::NODE_TYPE_MISSING, 'The node type "' . $nodeTypeName->jsonSerialize() . '" is not found; so the node should be removed (or converted)', function () use ($nodeAggregate) {
                $this->readSideMemoryCacheManager->disableCache();
                return $this->removeNodeAggregate($nodeAggregate);
            });
        }
    }

    protected function getNodeTypeManager(): NodeTypeManager
    {
        return $this->nodeTypeManager;
    }
}
