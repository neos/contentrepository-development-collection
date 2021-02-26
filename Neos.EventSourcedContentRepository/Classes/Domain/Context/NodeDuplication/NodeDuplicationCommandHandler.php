<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\ContentDimensionZookeeper;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\InterDimensionalVariationGraph;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamRepository;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature\ConstraintChecks;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\Command\Dto\NodeAggregateIdentifierMapping;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\Command\CopyNodesRecursively;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Ramsey\Uuid\Uuid;

final class NodeDuplicationCommandHandler
{
    use ConstraintChecks;

    protected NodeAggregateCommandHandler $nodeAggregateCommandHandler;

    protected ContentGraphInterface $contentGraph;

    protected ContentStreamRepository $contentStreamRepository;

    protected NodeTypeManager $nodeTypeManager;

    protected ReadSideMemoryCacheManager $readSideMemoryCacheManager;

    protected NodeAggregateEventPublisher $nodeAggregateEventPublisher;

    protected DimensionSpacePointSet $allowedDimensionSubspace;

    protected InterDimensionalVariationGraph $interDimensionalVariationGraph;

    public function __construct(
        NodeAggregateCommandHandler $nodeAggregateCommandHandler,
        ContentGraphInterface $contentGraph,
        ContentStreamRepository $contentStreamRepository,
        NodeTypeManager $nodeTypeManager,
        ReadSideMemoryCacheManager $readSideMemoryCacheManager,
        NodeAggregateEventPublisher $nodeAggregateEventPublisher,
        ContentDimensionZookeeper $contentDimensionZookeeper,
        InterDimensionalVariationGraph $interDimensionalVariationGraph
    ) {
        $this->nodeAggregateCommandHandler = $nodeAggregateCommandHandler;
        $this->contentGraph = $contentGraph;
        $this->contentStreamRepository = $contentStreamRepository;
        $this->nodeTypeManager = $nodeTypeManager;
        $this->readSideMemoryCacheManager = $readSideMemoryCacheManager;
        $this->nodeAggregateEventPublisher = $nodeAggregateEventPublisher;
        $this->allowedDimensionSubspace = $contentDimensionZookeeper->getAllowedDimensionSubspace();
        $this->interDimensionalVariationGraph = $interDimensionalVariationGraph;
    }

    protected function getContentGraph(): ContentGraphInterface
    {
        return $this->contentGraph;
    }

    protected function getContentStreamRepository(): ContentStreamRepository
    {
        return $this->contentStreamRepository;
    }

    protected function getNodeTypeManager(): NodeTypeManager
    {
        return $this->nodeTypeManager;
    }

    protected function getAllowedDimensionSubspace(): DimensionSpacePointSet
    {
        return $this->allowedDimensionSubspace;
    }

    /**
     * @param CopyNodesRecursively $command
     * @throws \Neos\ContentRepository\Exception\NodeConstraintException
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     * @throws \Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamDoesNotExistYet
     */
    public function handleCopyNodesRecursively(CopyNodesRecursively $command)
    {
        $this->readSideMemoryCacheManager->disableCache();

        // Basic constraints (Content Stream / Dimension Space Point / Node Type of to-be-inserted root node)
        $this->requireContentStreamToExist($command->getContentStreamIdentifier());
        $this->requireDimensionSpacePointToExist($command->getTargetDimensionSpacePoint());
        $nodeType = $this->requireNodeType($command->getNodeToInsert()->getNodeTypeName());
        $this->requireNodeTypeToNotBeOfTypeRoot($nodeType);

        // Constraint: Does the target parent node allow nodes of this type?
        // NOTE: we only check this for the *root* node of the to-be-inserted structure; and not for its
        // children (as we want to create the structure as-is; assuming it was already valid beforehand).
        $this->requireConstraintsImposedByAncestorsAreMet($command->getContentStreamIdentifier(), $nodeType, $command->getTargetNodeName(), [$command->getTargetParentNodeAggregateIdentifier()]);

        // Constraint: The new nodeAggregateIdentifiers are not allowed to exist yet.
        $this->requireNewNodeAggregateIdentifiersToNotExist($command->getContentStreamIdentifier(), $command->getNodeAggregateIdentifierMapping());

        // Constraint: the parent node must exist in the command's DimensionSpacePoint as well
        $parentNodeAggregate = $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getTargetParentNodeAggregateIdentifier());
        if ($command->getTargetSucceedingSiblingNodeAggregateIdentifier()) {
            $this->requireProjectedNodeAggregate($command->getContentStreamIdentifier(), $command->getTargetSucceedingSiblingNodeAggregateIdentifier());
        }
        $this->requireNodeAggregateToCoverDimensionSpacePoint($parentNodeAggregate, $command->getTargetDimensionSpacePoint());

        // Calculate Covered Dimension Space Points: All points being specializations of the
        // given DSP, where the parent also exists.
        $specializations = $this->interDimensionalVariationGraph->getSpecializationSet($command->getTargetDimensionSpacePoint());
        $coveredDimensionSpacePoints = $specializations->getIntersection($parentNodeAggregate->getCoveredDimensionSpacePoints());

        // Constraint: The node name must be free in all these dimension space points
        if ($command->getTargetNodeName()) {
            $this->requireNodeNameToBeUnoccupied(
                $command->getContentStreamIdentifier(),
                $command->getTargetNodeName(),
                $command->getTargetParentNodeAggregateIdentifier(),
                $command->getTargetDimensionSpacePoint(),
                $coveredDimensionSpacePoints
            );
        }

        // Now, we can start creating the recursive structure.
        $events = DomainEvents::createEmpty();
        $this->nodeAggregateEventPublisher->withCommand($command, function () use ($command, $nodeType, $parentNodeAggregate, $coveredDimensionSpacePoints, &$events) {
            $this->createEventsForNodeToInsert(
                $command->getContentStreamIdentifier(),
                $command->getTargetDimensionSpacePoint(),
                $coveredDimensionSpacePoints,
                $command->getTargetParentNodeAggregateIdentifier(),
                $command->getTargetSucceedingSiblingNodeAggregateIdentifier(),
                $command->getTargetNodeName(),
                $command->getNodeToInsert(),
                $command->getNodeAggregateIdentifierMapping(),
                $command->getInitiatingUserIdentifier(),
                $events
            );

            $contentStreamEventStreamName = ContentStreamEventStreamName::fromContentStreamIdentifier($command->getContentStreamIdentifier());
            $this->nodeAggregateEventPublisher->publishMany(
                $contentStreamEventStreamName->getEventStreamName(),
                $events
            );
        });

        return CommandResult::fromPublishedEvents($events);
    }

    private function requireNewNodeAggregateIdentifiersToNotExist(ContentStreamIdentifier $contentStreamIdentifier, NodeAggregateIdentifierMapping $nodeAggregateIdentifierMapping)
    {
        foreach ($nodeAggregateIdentifierMapping->getAllNewNodeAggregateIdentifiers() as $nodeAggregateIdentifier) {
            $this->requireProjectedNodeAggregateToNotExist($contentStreamIdentifier, $nodeAggregateIdentifier);
        }
    }

    private function createEventsForNodeToInsert(
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint,
        DimensionSpacePointSet $coveredDimensionSpacePoints,
        NodeAggregateIdentifier $targetParentNodeAggregateIdentifier,
        ?NodeAggregateIdentifier $targetSucceedingSiblingNodeAggregateIdentifier,
        NodeName $targetNodeName,
        Command\Dto\NodeSubtreeSnapshot $nodeToInsert,
        NodeAggregateIdentifierMapping $nodeAggregateIdentifierMapping,
        UserIdentifier $initiatingUserIdentifier,
        DomainEvents &$events
    ) {
        $events = $events->appendEvent(
            DecoratedEvent::addIdentifier(
                new NodeAggregateWithNodeWasCreated(
                    $contentStreamIdentifier,
                    $nodeAggregateIdentifierMapping->getNewNodeAggregateIdentifier($nodeToInsert->getNodeAggregateIdentifier()),
                    $nodeToInsert->getNodeTypeName(),
                    OriginDimensionSpacePoint::fromDimensionSpacePoint($dimensionSpacePoint),
                    $coveredDimensionSpacePoints,
                    $targetParentNodeAggregateIdentifier,
                    $targetNodeName,
                    $nodeToInsert->getPropertyValues(),
                    $nodeToInsert->getNodeAggregateClassification(),
                    $initiatingUserIdentifier,
                    $targetSucceedingSiblingNodeAggregateIdentifier
                ),
                Uuid::uuid4()->toString()
            )
        );

        foreach ($nodeToInsert->getChildNodesToInsert() as $childNodeToInsert) {
            $this->createEventsForNodeToInsert(
                $contentStreamIdentifier,
                $dimensionSpacePoint,
                $coveredDimensionSpacePoints,
                $nodeAggregateIdentifierMapping->getNewNodeAggregateIdentifier($nodeToInsert->getNodeAggregateIdentifier()), // the just-inserted node becomes the new parent node Identifier
                null, // $childNodesToInsert is already in the correct order; so appending only is fine.
                $childNodeToInsert->getNodeName(),
                $childNodeToInsert,
                $nodeAggregateIdentifierMapping,
                $initiatingUserIdentifier,
                $events
            );
        }
    }
}
