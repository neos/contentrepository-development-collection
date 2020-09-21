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
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeAggregate;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Ramsey\Uuid\Uuid;

trait TetheredNodeInternals
{
    use NodeVariationInternals;

    abstract protected function getContentGraph(): ContentGraphInterface;

    abstract protected function getDefaultPropertyValues(NodeType $nodeType): SerializedPropertyValues;

    abstract protected function createEventsForVariations(ContentStreamIdentifier $contentStreamIdentifier, OriginDimensionSpacePoint $sourceOrigin, OriginDimensionSpacePoint $targetOrigin, ReadableNodeAggregateInterface $nodeAggregate): DomainEvents;

    /**
     * This is the remediation action for non-existing tethered nodes.
     * It handles two cases:
     * - there is no tethered node IN ANY DimensionSpacePoint -> we can simply create it
     * - there is a tethered node already in some DimensionSpacePoint -> we need to specialize/generalize/... the other Tethered Node.
     *
     * @param NodeAggregate $parentNodeAggregate the node aggregate of the parent node
     * @param NodeInterface $parentNode the parent node underneath the tethered node should be.
     * @param NodeName $tetheredNodeName name of the edge towards the tethered node
     * @param NodeType $expectedTetheredNodeType expected node type of the tethered node
     * @param $command
     * @return CommandResult
     * @throws \Exception
     */
    protected function createEventsForMissingTetheredNode(ReadableNodeAggregateInterface $parentNodeAggregate, NodeInterface $parentNode, NodeName $tetheredNodeName, NodeType $expectedTetheredNodeType): DomainEvents
    {
        $childNodeAggregates = $this->getContentGraph()->findChildNodeAggregatesByName($parentNode->getContentStreamIdentifier(), $parentNode->getNodeAggregateIdentifier(), $tetheredNodeName);
        if (count($childNodeAggregates) === 0) {

            // there is no tethered child node aggregate already; let's create it!
            $events = DomainEvents::withSingleEvent(
                DecoratedEvent::addIdentifier(
                    new NodeAggregateWithNodeWasCreated(
                        $parentNode->getContentStreamIdentifier(),
                        NodeAggregateIdentifier::forAutoCreatedChildNode($tetheredNodeName, $parentNode->getNodeAggregateIdentifier()),
                        NodeTypeName::fromString($expectedTetheredNodeType->getName()),
                        $parentNode->getOriginDimensionSpacePoint(),
                        $parentNodeAggregate->getCoverageByOccupant($parentNode->getOriginDimensionSpacePoint()),
                        $parentNode->getNodeAggregateIdentifier(),
                        $tetheredNodeName,
                        $this->getDefaultPropertyValues($expectedTetheredNodeType),
                        NodeAggregateClassification::tethered()
                    ),
                    Uuid::uuid4()->toString()
                )
            );

        } elseif (count($childNodeAggregates) === 1) {
            $childNodeAggregate = current($childNodeAggregates);
            if (!$childNodeAggregate->isTethered()) {
                throw new \RuntimeException('We found a child node aggregate through the given node path; but it is not tethered. We do not support re-tethering yet (as this case should happen very rarely as far as we think).');
            }

            $childNodeSource = $childNodeAggregate->getNodes()[0];
            $events = $this->createEventsForVariations($parentNode->getContentStreamIdentifier(), $childNodeSource->getOriginDimensionSpacePoint(), $parentNode->getOriginDimensionSpacePoint(), $parentNodeAggregate);
        } else {
            throw new \RuntimeException('There is >= 2 ChildNodeAggregates with the same name reachable from the parent - this is ambiguous and we should analyze how this may happen. That is very likely a bug.');
        }

        return $events;
    }
}
