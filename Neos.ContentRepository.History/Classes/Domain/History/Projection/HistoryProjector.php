<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Domain\Projection\History;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection as DatabaseConnection;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\History\Domain\History\AgentIdentifier;
use Neos\ContentRepository\History\Domain\History\HistoryEntryIdentifier;
use Neos\ContentRepository\History\Domain\History\HistoryEntryType;
use Neos\ContentRepository\History\Domain\History\Projection\HistoryEntryRecord;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateNameWasChanged;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateTypeWasChanged;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasDisabled;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasEnabled;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasMoved;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasRemoved;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeGeneralizationVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodePeerVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodePropertiesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeReferencesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeSpecializationVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\RootNodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
use Neos\Flow\Annotations as Flow;
use Neos\EventSourcing\Projection\ProjectorInterface;

/**
 * The history projector
 *
 * Writes history entries for all events related to node aggregates
 * @Flow\Scope("singleton")
 */
class HistoryProjector implements ProjectorInterface
{
    /**
     * @Flow\Inject
     * @var DbalClient
     */
    protected $dbalClient;

    /**
     * @Flow\Inject
     * @var WorkspaceFinder
     */
    protected $workspaceFinder;

    private const TABLE_NAME = 'neos_neos_history_entry';

    public function isEmpty(): bool
    {
        return $this->getDatabaseConnection()
            ->executeQuery('SELECT count(*) FROM ' . self::TABLE_NAME)
            ->fetchColumn() === 0;
    }

    public function reset(): void
    {
        $this->transactional(function () {
            $this->getDatabaseConnection()->executeQuery('TRUNCATE table ' . self::TABLE_NAME);
        });
    }

    public function whenRootNodeAggregateWithNodeWasCreated(RootNodeAggregateWithNodeWasCreated $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::created(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'nodeTypeName' => $event->getNodeTypeName()
                ]
            );

            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeAggregateWithNodeWasCreated(NodeAggregateWithNodeWasCreated $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::created(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'nodeTypeName' => $event->getNodeTypeName(),
                    'originDimensionSpacePoint' => $event->getOriginDimensionSpacePoint(),
                    'parentNodeAggregateIdentifier' => $event->getParentNodeAggregateIdentifier(),
                    'nodeName' => $event->getNodeName(),
                    'initialPropertyValues' => $event->getInitialPropertyValues()
                ]
            );

            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeAggregateWasRenamed(NodeAggregateNameWasChanged $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::renamed(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'newNodeName' => $event->getNewNodeName()
                ]
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeAggregateWasRetyped(NodeAggregateTypeWasChanged $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::retyped(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'newNodeTypeName' => $event->getNewNodeTypeName()
                ]
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeAggregateWasDisabled(NodeAggregateWasDisabled $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::disabled(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                []
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeAggregateWasEnabled(NodeAggregateWasEnabled $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::enabled(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                []
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeAggregateWasMoved(NodeAggregateWasMoved $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::moved(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'nodeMoveMappings' => $event->getNodeMoveMappings()
                ]
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeAggregateWasRemoved(NodeAggregateWasRemoved $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::removed(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'affectedOccupiedDimensionSpacePoints' => $event->getAffectedOccupiedDimensionSpacePoints()
                ]
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeGeneralizationVariantWasCreated(NodeGeneralizationVariantWasCreated $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $this->writeVariationEntry(
                $event->getNodeAggregateIdentifier(),
                $event->getInitiatingUserIdentifier(),
                $event->getSourceOrigin(),
                $event->getGeneralizationOrigin()
            );
        }
    }

    public function whenNodeSpecializationVariantWasCreated(NodeSpecializationVariantWasCreated $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $this->writeVariationEntry(
                $event->getNodeAggregateIdentifier(),
                $event->getInitiatingUserIdentifier(),
                $event->getSourceOrigin(),
                $event->getSpecializationOrigin()
            );
        }
    }

    public function whenNodePeerVariantWasCreated(NodePeerVariantWasCreated $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $this->writeVariationEntry(
                $event->getNodeAggregateIdentifier(),
                $event->getInitiatingUserIdentifier(),
                $event->getSourceOrigin(),
                $event->getPeerOrigin()
            );
        }
    }

    private function writeVariationEntry(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        UserIdentifier $userIdentifier,
        DimensionSpacePoint $sourceOrigin,
        DimensionSpacePoint $targetOrigin
    ): void {
        $historyEntryRecord = new HistoryEntryRecord(
            HistoryEntryIdentifier::create(),
            $nodeAggregateIdentifier,
            HistoryEntryType::varied(),
            AgentIdentifier::fromUserIdentifier($userIdentifier),
            new \DateTimeImmutable(),
            [
                'sourceOrigin' => $sourceOrigin,
                'targetOrigin' => $targetOrigin
            ]
        );
        $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
    }

    public function whenNodePropertiesWereSet(NodePropertiesWereSet $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getNodeAggregateIdentifier(),
                HistoryEntryType::modified(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'propertyValues' => $event->getPropertyValues()
                ]
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    public function whenNodeReferencesWereSet(NodeReferencesWereSet $event): void
    {
        if ($this->isLiveWorkspaceAffected($event->getContentStreamIdentifier())) {
            $historyEntryRecord = new HistoryEntryRecord(
                HistoryEntryIdentifier::create(),
                $event->getSourceNodeAggregateIdentifier(),
                HistoryEntryType::referenced(),
                AgentIdentifier::fromUserIdentifier($event->getInitiatingUserIdentifier()),
                new \DateTimeImmutable(),
                [
                    'sourceOriginDimensionSpacePoint' => $event->getSourceOriginDimensionSpacePoint(),
                    'destinationNodeAggregateIdentifiers' => $event->getDestinationNodeAggregateIdentifiers(),
                    'referenceName' => $event->getReferenceName(),
                ]
            );
            $historyEntryRecord->addToDatabase($this->getDatabaseConnection());
        }
    }

    private function isLiveWorkspaceAffected(ContentStreamIdentifier $contentStreamIdentifier): bool
    {
        $workspace = $this->workspaceFinder->findOneByCurrentContentStreamIdentifier($contentStreamIdentifier);

        return $workspace->getWorkspaceName()->isLive();
    }

    private function transactional(callable $callable): void
    {
        return $this->getDatabaseConnection()->transactional($callable);
    }

    private function getDatabaseConnection(): DatabaseConnection
    {
        return $this->dbalClient->getConnection();
    }
}
