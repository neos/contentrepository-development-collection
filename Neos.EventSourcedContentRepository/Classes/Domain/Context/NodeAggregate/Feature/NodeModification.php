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

use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeProperties;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodePropertiesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodePropertyScope;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ScopedPropertyValues;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;

trait NodeModification
{
    abstract protected function getReadSideMemoryCacheManager(): ReadSideMemoryCacheManager;

    abstract protected function getNodeAggregateEventPublisher(): NodeAggregateEventPublisher;

    abstract protected function requireProjectedNodeAggregate(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): ReadableNodeAggregateInterface;

    abstract protected function requireNodeType(NodeTypeName $nodeTypeName): NodeType;

    public function handleSetNodeProperties(SetNodeProperties $command): CommandResult
    {
        $this->getReadSideMemoryCacheManager()->disableCache();

        $this->requireContentStreamToExist($command->getContentStreamIdentifier());
        $nodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        $this->requireDimensionSpacePointToExist($command->getOriginDimensionSpacePoint());
        $this->requireNodeAggregateToOccupyDimensionSpacePoint($nodeAggregate, $command->getOriginDimensionSpacePoint());
        $nodeType = $this->requireNodeType($nodeAggregate->getNodeTypeName());
        $propertyValuesByScope = [
            NodePropertyScope::SCOPE_NODE => [],
            NodePropertyScope::SCOPE_NODE_AGGREGATE => []
        ];
        foreach ($command->getPropertyValues() as $rawPropertyName => $propertyValue) {
            $propertyName = PropertyName::fromString($rawPropertyName);
            $this->requireNodeTypeToDeclareProperty($nodeType, $propertyName);
            $this->requirePropertyToBeDeclaredAsType($nodeType, $propertyName, $propertyValue->getType());
            $propertyScope = NodePropertyScope::fromNodeTypeAndPropertyName($nodeType, $propertyName);
            $propertyValuesByScope[(string) $propertyScope][(string) $propertyName] = $propertyValue;
        }

        $domainEvents = null;
        $this->getNodeAggregateEventPublisher()->withCommand($command, function () use ($command, &$domainEvents, $nodeAggregate, $nodeType) {
            $contentStreamIdentifier = $command->getContentStreamIdentifier();

            $scopedPropertyValues = ScopedPropertyValues::separateFromPropertyValues($nodeType, $command->getPropertyValues());

            $events = [];
            $nodeScopedPropertyValues = $scopedPropertyValues->get(NodePropertyScope::node());
            if ($nodeScopedPropertyValues) {
                $events[] = EventWithIdentifier::create(
                    new NodePropertiesWereSet(
                        $contentStreamIdentifier,
                        $command->getNodeAggregateIdentifier(),
                        $command->getOriginDimensionSpacePoint(),
                        $nodeScopedPropertyValues
                    )
                );
            }

            $nodeAggregateScopedPropertyValues = $scopedPropertyValues->get(NodePropertyScope::nodeAggregate());
            if ($nodeAggregateScopedPropertyValues) {
                foreach ($nodeAggregate->getOccupiedDimensionSpacePoints() as $originDimensionSpacePoint)
                $events[] = EventWithIdentifier::create(
                    new NodePropertiesWereSet(
                        $contentStreamIdentifier,
                        $command->getNodeAggregateIdentifier(),
                        $originDimensionSpacePoint,
                        $nodeAggregateScopedPropertyValues
                    )
                );
            }

            $domainEvents = DomainEvents::fromArray($events);
            $this->getNodeAggregateEventPublisher()->publishMany(
                ContentStreamEventStreamName::fromContentStreamIdentifier($contentStreamIdentifier)->getEventStreamName(),
                $domainEvents
            );
        });

        return CommandResult::fromPublishedEvents($domainEvents);
    }
}
