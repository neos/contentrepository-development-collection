<?php declare(strict_types=1);
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

use Neos\ContentRepository\DimensionSpace\DimensionSpace\InterDimensionalVariationGraph;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeProperties;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetSerializedNodeProperties;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodePropertiesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\PropertyConversionService;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\PropertyScope;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Ramsey\Uuid\Uuid;

trait NodeModification
{
    abstract protected function getReadSideMemoryCacheManager(): ReadSideMemoryCacheManager;

    abstract protected function getNodeAggregateEventPublisher(): NodeAggregateEventPublisher;

    abstract protected function getPropertyConversionService(): PropertyConversionService;

    abstract protected function getInterDimensionalVariationGraph(): InterDimensionalVariationGraph;

    abstract protected function requireNodeType(NodeTypeName $nodeTypeName): NodeType;

    abstract protected function requireProjectedNodeAggregate(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): ReadableNodeAggregateInterface;

    public function handleSetNodeProperties(SetNodeProperties $command): CommandResult
    {
        $this->requireContentStreamToExist($command->getContentStreamIdentifier());
        $this->requireDimensionSpacePointToExist($command->getOriginDimensionSpacePoint());
        $nodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        $this->requireNodeAggregateToNotBeRoot($nodeAggregate);
        $this->requireNodeAggregateToOccupyDimensionSpacePoint($nodeAggregate, $command->getOriginDimensionSpacePoint());
        $nodeType = $this->requireNodeType($nodeAggregate->getNodeTypeName());
        foreach ($command->getPropertyValues()->getValues() as $propertyName => $value) {
            $this->requireNodeTypeToDeclareProperty($nodeType, PropertyName::fromString($propertyName));
        }
        $serializedPropertyValues = $this->getPropertyConversionService()->serializePropertyValues($command->getPropertyValues(), $nodeType);

        $newCommand = new SetSerializedNodeProperties(
            $command->getContentStreamIdentifier(),
            $command->getNodeAggregateIdentifier(),
            $command->getOriginDimensionSpacePoint(),
            $serializedPropertyValues
        );

        return $this->handleSetSerializedNodeProperties($newCommand);
    }

    /**
     * @param SetSerializedNodeProperties $command
     * @return CommandResult
     * @internal instead, use {@see self::handleSetNodeProperties} instead publicly.
     */
    public function handleSetSerializedNodeProperties(SetSerializedNodeProperties $command): CommandResult
    {
        $this->getReadSideMemoryCacheManager()->disableCache();

        $nodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifier());
        $events = null;
        $this->getNodeAggregateEventPublisher()->withCommand($command, function () use ($command, &$events, $nodeAggregate) {
            $serializedPropertiesByScope = $this->separateSerializedPropertiesByScope(
                $command->getPropertyValues(),
                $this->requireNodeType($nodeAggregate->getNodeTypeName())
            );

            $decoratedEvents = [];
            foreach ($serializedPropertiesByScope as $scope => $serializedPropertyValues) {
                $propertyScope = PropertyScope::fromString($scope);

                if ($propertyScope->isNode()) {
                    $affectedOrigins = new OriginDimensionSpacePointSet([$command->getOriginDimensionSpacePoint()]);
                } elseif ($propertyScope->isSpecializations()) {
                    $affectedOrigins = $nodeAggregate->getOccupiedDimensionSpacePoints()
                        ->getIntersection(OriginDimensionSpacePointSet::fromDimensionSpacePointSet(
                            $this->getInterDimensionalVariationGraph()->getSpecializationSet($command->getOriginDimensionSpacePoint())
                        ));
                } elseif ($propertyScope->isNodeAggregate()) {
                    $affectedOrigins = $nodeAggregate->getOccupiedDimensionSpacePoints();
                } else {
                    $affectedOrigins = new OriginDimensionSpacePointSet([]);
                }

                foreach ($affectedOrigins as $originDimensionSpacePoint) {
                    $decoratedEvents[] = DecoratedEvent::addIdentifier(
                        new NodePropertiesWereSet(
                            $command->getContentStreamIdentifier(),
                            $command->getNodeAggregateIdentifier(),
                            $originDimensionSpacePoint,
                            $serializedPropertyValues
                        ),
                        Uuid::uuid4()->toString()
                    );
                }
            }

            $events = DomainEvents::fromArray($decoratedEvents);

            $this->getNodeAggregateEventPublisher()->publishMany(
                ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier())->getEventStreamName(),
                $events
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    /**
     * @param SerializedPropertyValues $serializedPropertyValues
     * @param NodeType $nodeType
     * @return array|SerializedPropertyValues[]
     */
    private function separateSerializedPropertiesByScope(SerializedPropertyValues $serializedPropertyValues, NodeType $nodeType): array
    {
        $serializedPropertiesByScope = [];
        foreach ($serializedPropertyValues->getValues() as $propertyName => $serializedPropertyValue) {
            $declaredScope = $nodeType->getConfiguration('properties.' . $propertyName . '.scope');
            $propertyScope = $declaredScope
                ? PropertyScope::fromString($declaredScope)
                : PropertyScope::node();

            $serializedPropertiesByScope[(string)$propertyScope][$propertyName] = $serializedPropertyValue;
        }

        array_walk($serializedPropertiesByScope, function (array &$scopedSerializedPropertiesByName) {
            $scopedSerializedPropertiesByName = SerializedPropertyValues::fromArray($scopedSerializedPropertiesByName);
        });

        return $serializedPropertiesByScope;
    }
}
