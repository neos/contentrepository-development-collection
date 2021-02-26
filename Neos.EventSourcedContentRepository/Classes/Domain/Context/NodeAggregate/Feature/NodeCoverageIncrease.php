<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream;
use Neos\ContentRepository\DimensionSpace\DimensionSpace;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\IncreaseNodeAggregateCoverage;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateCoverageWasIncreased;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\PropertyConversionService;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Ramsey\Uuid\Uuid;

trait NodeCoverageIncrease
{
    abstract protected function getReadSideMemoryCacheManager(): ReadSideMemoryCacheManager;

    abstract protected function getNodeAggregateEventPublisher(): NodeAggregateEventPublisher;

    abstract protected function getPropertyConversionService(): PropertyConversionService;

    abstract protected function getInterDimensionalVariationGraph(): DimensionSpace\InterDimensionalVariationGraph;

    abstract protected function getAllowedDimensionSubspace(): DimensionSpacePointSet;

    abstract protected function areAncestorNodeTypeConstraintChecksEnabled(): bool;

    abstract protected function requireNodeType(NodeTypeName $nodeTypeName): NodeType;

    abstract protected function getContentGraph(): ContentGraphInterface;

    abstract protected function requireProjectedNodeAggregate(ContentStreamIdentifier $contentStreamIdentifier, NodeAggregateIdentifier $nodeAggregateIdentifier): ReadableNodeAggregateInterface;

    public function handleIncreaseNodeAggregateCoverage(IncreaseNodeAggregateCoverage $command): CommandResult
    {
        $this->getReadSideMemoryCacheManager()->disableCache();

        $this->requireContentStreamToExist($command->getContentStreamIdentifier());
        $nodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        foreach ($command->getAdditionalCoverage() as $dimensionSpacePoint) {
            $this->requireDimensionSpacePointToBeSpecialization($dimensionSpacePoint, $command->getOriginDimensionSpacePoint());
        }
        $this->requireNodeAggregateToNotBeRoot($nodeAggregate);
        $this->requireNodeAggregateToBeUntethered($nodeAggregate);
        $this->requireNodeAggregateToOccupyDimensionSpacePoint($nodeAggregate, $command->getOriginDimensionSpacePoint());
        $this->requireNodeAggregateToNotCoverDimensionSpacePoints($nodeAggregate, $command->getAdditionalCoverage());
        $parentNodeAggregate = $this->getContentGraph()->findParentNodeAggregateByChildOriginDimensionSpacePoint(
            $command->getContentStreamIdentifier(),
            $command->getNodeAggregateIdentifier(),
            $command->getOriginDimensionSpacePoint()
        );
        $this->requireNodeAggregateToCoverDimensionSpacePoints($parentNodeAggregate, $command->getAdditionalCoverage());
        if ($nodeAggregate->isNamed()) {
            $this->requireNodeNameToBeUncovered(
                $command->getContentStreamIdentifier(),
                $nodeAggregate->getNodeName(),
                $parentNodeAggregate->getIdentifier(),
                $command->getAdditionalCoverage()
            );
        }
        $events = DomainEvents::createEmpty();
        $this->getNodeAggregateEventPublisher()->withCommand($command, function () use ($command, &$events) {
            $events = DomainEvents::fromArray(
                $this->collectNodeCoverageIncreaseEvents($command, $command->getNodeAggregateIdentifier(), [])
            );

            $contentStreamEventStreamName = ContentStream\ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier());
            $this->getNodeAggregateEventPublisher()->publishMany(
                $contentStreamEventStreamName->getEventStreamName(),
                $events
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    private function collectNodeCoverageIncreaseEvents(
        IncreaseNodeAggregateCoverage $command,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        array $events
    ): array {
        $events[] = DecoratedEvent::addIdentifier(
            new NodeAggregateCoverageWasIncreased(
                $command->getContentStreamIdentifier(),
                $nodeAggregateIdentifier,
                $command->getOriginDimensionSpacePoint(),
                $command->getAdditionalCoverage(),
                $command->getInitiatingUserIdentifier()
            ),
            Uuid::uuid4()->toString()
        );
        foreach ($this->getContentGraph()->findTetheredChildNodeAggregates(
            $command->getContentStreamIdentifier(),
            $nodeAggregateIdentifier
        ) as $childNodeAggregate) {
            $events = $this->collectNodeCoverageIncreaseEvents($command, $childNodeAggregate->getIdentifier(), $events);
        }

        return $events;
    }
}
