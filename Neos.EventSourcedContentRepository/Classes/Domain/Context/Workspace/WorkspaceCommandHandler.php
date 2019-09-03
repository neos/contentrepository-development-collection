<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\Workspace;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\Exception\DimensionSpacePointNotFound;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Command\CreateContentStream;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Command\ForkContentStream;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Event\ContentStreamWasForked;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamAlreadyExists;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamDoesNotExistYet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\ChangeNodeAggregateName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\DisableNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\RemoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeProperties;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeReferences;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\EnableNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\CopyableAcrossContentStreamsInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregatesTypeIsAmbiguous;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeNameIsAlreadyOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\MatchableWithNodeAddressInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\MoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\PublishWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\RebaseWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\RootWorkspaceWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasRebased;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\BaseWorkspaceDoesNotExist;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\BaseWorkspaceHasBeenModifiedInTheMeantime;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\WorkspaceAlreadyExists;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\WorkspaceDoesNotExist;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventEnvelope;
use Neos\EventSourcing\EventStore\EventStoreManager;
use Neos\EventSourcing\EventStore\Exception\ConcurrencyException;
use Neos\EventSourcing\EventStore\Exception\EventStreamNotFoundException;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Annotations as Flow;

/**
 * WorkspaceCommandHandler
 */
final class WorkspaceCommandHandler
{
    /**
     * @Flow\Inject
     * @var WorkspaceFinder
     */
    protected $workspaceFinder;

    /**
     * @Flow\Inject
     * @var NodeAggregateCommandHandler
     */
    protected $nodeAggregateCommandHandler;

    /**
     * @Flow\Inject
     * @var ContentStreamCommandHandler
     */
    protected $contentStreamCommandHandler;

    /**
     * @Flow\Inject
     * @var EventStoreManager
     */
    protected $eventStoreManager;

    /**
     * @Flow\Inject
     * @var ReadSideMemoryCacheManager
     */
    protected $readSideMemoryCacheManager;

    /**
     * @Flow\Inject
     * @var ContentGraphInterface
     */
    protected $contentGraph;

    /**
     * @param CreateWorkspace $command
     * @return CommandResult
     * @throws BaseWorkspaceDoesNotExist
     * @throws ContentStreamAlreadyExists
     * @throws ContentStreamDoesNotExistYet
     * @throws WorkspaceAlreadyExists
     */
    public function handleCreateWorkspace(CreateWorkspace $command): CommandResult
    {
        $this->readSideMemoryCacheManager->disableCache();

        $existingWorkspace = $this->workspaceFinder->findOneByName($command->getWorkspaceName());
        if ($existingWorkspace !== null) {
            throw new WorkspaceAlreadyExists(sprintf('The workspace %s already exists', $command->getWorkspaceName()), 1505830958921);
        }

        $baseWorkspace = $this->workspaceFinder->findOneByName($command->getBaseWorkspaceName());
        if ($baseWorkspace === null) {
            throw new BaseWorkspaceDoesNotExist(sprintf('The workspace %s (base workspace of %s) does not exist', $command->getBaseWorkspaceName(), $command->getWorkspaceName()), 1513890708);
        }

        // TODO: CONCEPT OF EDITING SESSION IS TOTALLY MISSING SO FAR!!!!
        // When the workspace is created, we first have to fork the content stream

        $commandResult = CommandResult::createEmpty();
        $commandResult = $commandResult->merge($this->contentStreamCommandHandler->handleForkContentStream(
            new ForkContentStream(
                $command->getContentStreamIdentifier(),
                $baseWorkspace->getCurrentContentStreamIdentifier()
            )
        ));

        $streamName = StreamName::fromString('Neos.ContentRepository:Workspace:' . $command->getWorkspaceName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new WorkspaceWasCreated(
                    $command->getWorkspaceName(),
                    $command->getBaseWorkspaceName(),
                    $command->getWorkspaceTitle(),
                    $command->getWorkspaceDescription(),
                    $command->getInitiatingUserIdentifier(),
                    $command->getContentStreamIdentifier(),
                    $command->getWorkspaceOwner()
                )
            )
        );

        $eventStore->commit($streamName, $events);
        $commandResult = $commandResult->merge(CommandResult::fromPublishedEvents($events));
        return $commandResult;
    }

    /**
     * @param CreateRootWorkspace $command
     * @return CommandResult
     * @throws WorkspaceAlreadyExists
     * @throws ContentStreamAlreadyExists
     */
    public function handleCreateRootWorkspace(CreateRootWorkspace $command): CommandResult
    {
        $this->readSideMemoryCacheManager->disableCache();

        $existingWorkspace = $this->workspaceFinder->findOneByName($command->getWorkspaceName());
        if ($existingWorkspace !== null) {
            throw new WorkspaceAlreadyExists(sprintf('The workspace %s already exists', $command->getWorkspaceName()), 1505848624450);
        }

        $commandResult = CommandResult::createEmpty();
        $contentStreamIdentifier = $command->getContentStreamIdentifier();
        $commandResult = $commandResult->merge($this->contentStreamCommandHandler->handleCreateContentStream(
            new CreateContentStream(
                $contentStreamIdentifier,
                $command->getInitiatingUserIdentifier()
            )
        ));

        $streamName = StreamName::fromString('Neos.ContentRepository:Workspace:' . $command->getWorkspaceName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new RootWorkspaceWasCreated(
                    $command->getWorkspaceName(),
                    $command->getWorkspaceTitle(),
                    $command->getWorkspaceDescription(),
                    $command->getInitiatingUserIdentifier(),
                    $contentStreamIdentifier
                )
            )
        );

        $eventStore->commit($streamName, $events);
        $commandResult = $commandResult->merge(CommandResult::fromPublishedEvents($events));

        return $commandResult;
    }

    /**
     * @param PublishWorkspace $command
     * @return CommandResult
     * @throws BaseWorkspaceDoesNotExist
     * @throws WorkspaceDoesNotExist
     * @throws EventStreamNotFoundException
     * @throws \Exception
     */
    public function handlePublishWorkspace(PublishWorkspace $command): CommandResult
    {
        $this->readSideMemoryCacheManager->disableCache();

        $workspace = $this->workspaceFinder->findOneByName($command->getWorkspaceName());
        if ($workspace === null) {
            throw new WorkspaceDoesNotExist(sprintf('The workspace %s does not exist', $command->getWorkspaceName()), 1513924741);
        }
        $baseWorkspace = $this->workspaceFinder->findOneByName($workspace->getBaseWorkspaceName());
        if ($baseWorkspace === null) {
            throw new BaseWorkspaceDoesNotExist(sprintf('The workspace %s (base workspace of %s) does not exist', $workspace->getBaseWorkspaceName(), $command->getWorkspaceName()), 1513924882);
        }
        $commandResult = $this->publishContentStream($workspace->getCurrentContentStreamIdentifier(), $baseWorkspace->getCurrentContentStreamIdentifier());

        // After publishing a workspace, we need to again fork from Base.
        $newContentStream = ContentStreamIdentifier::create();
        $commandResult = $commandResult->merge(
            $this->contentStreamCommandHandler->handleForkContentStream(
                new ForkContentStream(
                    $newContentStream,
                    $baseWorkspace->getCurrentContentStreamIdentifier()
                )
            )
        );

        $commandResult->blockUntilProjectionsAreUpToDate();

        $streamName = StreamName::fromString('Neos.ContentRepository:Workspace:' . $command->getWorkspaceName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        // TODO: "Workspace was rebased" is probably the wrong name. We can rebase a content stream,
        // but not really a workspace. We can just change the ContentStream for a workspace.
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new WorkspaceWasRebased(
                    $command->getWorkspaceName(),
                    $newContentStream
                )
            )
        );
        // if we got so far without an Exception, we can switch the Workspace's active Content stream.
        $eventStore->commit($streamName, $events);
        return CommandResult::fromPublishedEvents($events);
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param ContentStreamIdentifier $baseContentStreamIdentifier
     * @return CommandResult
     * @throws BaseWorkspaceHasBeenModifiedInTheMeantime
     * @throws \Exception
     */
    private function publishContentStream(ContentStreamIdentifier $contentStreamIdentifier, ContentStreamIdentifier $baseContentStreamIdentifier): CommandResult
    {
        $contentStreamName = ContentStreamEventStreamName::fromContentStreamIdentifier($contentStreamIdentifier);
        $baseWorkspaceContentStreamName = ContentStreamEventStreamName::fromContentStreamIdentifier($baseContentStreamIdentifier);

        // TODO: please check the code below in-depth. it does:
        // - copy all events from the "user" content stream which implement "CopyableAcrossContentStreamsInterface"
        // - extract the initial ContentStreamWasForked event, to read the version of the source content stream when the fork occurred
        // - ensure that no other changes have been done in the meantime in the base content stream


        // NOTE: we need to use the ContentStream Event Stream as CATEGORY STREAM,
        // meaning we will search for all streams which have a PREFIX of the ContentStream;
        // so that we also find nested streams like the nested NodeAggregate streams, e.g.
        // "Neos.ContentRepository:ContentStream:b8f6042e-36c6-4bca-8c8e-2268e56a928e:NodeAggregate:new2-agg"
        $streamName = StreamName::forCategory((string)$contentStreamName->getEventStreamName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);

        /* @var $workspaceContentStream EventEnvelope[] */
        $workspaceContentStream = iterator_to_array($eventStore->load($streamName));

        $events = DomainEvents::createEmpty();
        foreach ($workspaceContentStream as $eventEnvelope) {
            $event = $eventEnvelope->getDomainEvent();
            if ($event instanceof CopyableAcrossContentStreamsInterface) {
                $events = $events->appendEvent(
                    EventWithIdentifier::create(
                        $event->createCopyForContentStream($baseContentStreamIdentifier)
                    )
                );
            }
        }

        // TODO: maybe we should also emit a "WorkspaceWasPublished" event? But on which content stream?
        $contentStreamWasForked = self::extractSingleForkedContentStreamEvent($workspaceContentStream);
        try {
            $eventStore = $this->eventStoreManager->getEventStoreForStreamName($baseWorkspaceContentStreamName->getEventStreamName());
            $eventStore->commit($baseWorkspaceContentStreamName->getEventStreamName(), $events, $contentStreamWasForked->getVersionOfSourceContentStream());
            return CommandResult::fromPublishedEvents($events);
        } catch (ConcurrencyException $e) {
            throw new BaseWorkspaceHasBeenModifiedInTheMeantime(sprintf('The base workspace has been modified in the meantime; please rebase. Expected version %d of source content stream %s', $contentStreamWasForked->getVersionOfSourceContentStream(), $baseContentStreamIdentifier));
        }
    }

    /**
     * @param array $stream
     * @return ContentStreamWasForked
     * @throws \Exception
     */
    private static function extractSingleForkedContentStreamEvent(array $stream): ContentStreamWasForked
    {
        $contentStreamWasForkedEvents = array_filter($stream, function (EventEnvelope $eventEnvelope) {
            return $eventEnvelope->getDomainEvent() instanceof ContentStreamWasForked;
        });

        if (count($contentStreamWasForkedEvents) !== 1) {
            throw new \Exception(sprintf('TODO: only can publish a content stream which has exactly one ContentStreamWasForked; we found %d', count($contentStreamWasForkedEvents)));
        }

        if (reset($contentStreamWasForkedEvents)->getDomainEvent() !== reset($stream)->getDomainEvent()) {
            throw new \Exception(sprintf('TODO: stream has to start with a single ContentStreamWasForked event, found %s', get_class(reset($stream)->getDomainEvent())));
        }

        return reset($contentStreamWasForkedEvents)->getDomainEvent();
    }

    /**
     * @param RebaseWorkspace $command
     * @return CommandResult
     * @throws BaseWorkspaceDoesNotExist
     * @throws WorkspaceDoesNotExist
     * @throws \Exception
     * @throws \Neos\EventSourcedContentRepository\Exception
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    public function handleRebaseWorkspace(RebaseWorkspace $command): CommandResult
    {
        $this->readSideMemoryCacheManager->disableCache();

        $workspace = $this->workspaceFinder->findOneByName($command->getWorkspaceName());
        if ($workspace === null) {
            throw new WorkspaceDoesNotExist(sprintf('The source workspace %s does not exist', $command->getWorkspaceName()), 1513924741);
        }
        $baseWorkspace = $this->workspaceFinder->findOneByName($workspace->getBaseWorkspaceName());

        if ($baseWorkspace === null) {
            throw new BaseWorkspaceDoesNotExist(sprintf('The workspace %s (base workspace of %s) does not exist', $workspace->getBaseWorkspaceName(), $command->getWorkspaceName()), 1513924882);
        }

        // TODO: please check the code below in-depth. it does:
        // - fork a new content stream
        // - extract the commands from the to-be-rebased content stream; and applies them on the new content stream
        $rebasedContentStream = ContentStreamIdentifier::create();
        $this->contentStreamCommandHandler->handleForkContentStream(
            new ForkContentStream(
                $rebasedContentStream,
                $baseWorkspace->getCurrentContentStreamIdentifier()
            )
        )->blockUntilProjectionsAreUpToDate();

        $workspaceContentStreamName = ContentStreamEventStreamName::fromContentStreamIdentifier($workspace->getCurrentContentStreamIdentifier());

        $originalCommands = $this->extractCommandsFromContentStreamMetadata($workspaceContentStreamName);
        foreach ($originalCommands as $originalCommand) {
            if (!($originalCommand instanceof CopyableAcrossContentStreamsInterface)) {
                throw new \RuntimeException('ERROR: The command ' . get_class($originalCommand) . ' does not implement CopyableAcrossContentStreamsInterface; but it should!');
            }

            // try to apply the command on the rebased content stream
            $commandToRebase = $originalCommand->createCopyForContentStream($rebasedContentStream);
            $this->applyCommand($commandToRebase)->blockUntilProjectionsAreUpToDate();
        }

        // if we got so far without an Exception, we can switch the Workspace's active Content stream.
        $streamName = StreamName::fromString('Neos.ContentRepository:Workspace:' . $command->getWorkspaceName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new WorkspaceWasRebased(
                    $command->getWorkspaceName(),
                    $rebasedContentStream
                )
            )
        );
        // if we got so far without an Exception, we can switch the Workspace's active Content stream.
        $eventStore->commit($streamName, $events);

        return CommandResult::fromPublishedEvents($events);
    }

    /**
     * @param ContentStreamEventStreamName $workspaceContentStreamName
     * @return array
     */
    private function extractCommandsFromContentStreamMetadata(ContentStreamEventStreamName $workspaceContentStreamName): array
    {
        // NOTE: we need to use the ContentStream Event Stream as CATEGORY STREAM,
        // meaning we will search for all streams which have a PREFIX of the ContentStream;
        // so that we also find nested streams like the nested NodeAggregate streams, e.g.
        // "Neos.ContentRepository:ContentStream:b8f6042e-36c6-4bca-8c8e-2268e56a928e:NodeAggregate:new2-agg"
        $streamName = StreamName::forCategory((string)$workspaceContentStreamName->getEventStreamName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);

        $workspaceContentStream = $eventStore->load($streamName);

        $commands = [];
        foreach ($workspaceContentStream as $eventAndRawEvent) {
            $metadata = $eventAndRawEvent->getRawEvent()->getMetadata();
            if (isset($metadata['commandClass'])) {
                $commandToRebaseClass = $metadata['commandClass'];
                $commandToRebasePayload = $metadata['commandPayload'];
                if (!method_exists($commandToRebaseClass, 'fromArray')) {
                    throw new \RuntimeException(sprintf('Command "%s" can\'t be rebased because it does not implement a static "fromArray" constructor', $commandToRebaseClass), 1547815341);
                }
                $commands[] = $commandToRebaseClass::fromArray($commandToRebasePayload);
            }
        }

        return $commands;
    }

    /**
     * @param $command
     * @return CommandResult
     * @throws \Neos\ContentRepository\Exception\NodeConstraintException
     * @throws \Neos\ContentRepository\Exception\NodeTypeNotFoundException
     * @throws ContentStreamDoesNotExistYet
     * @throws NodeNameIsAlreadyOccupied
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws DimensionSpacePointNotFound
     * @throws \Neos\Flow\Property\Exception
     * @throws \Neos\Flow\Security\Exception
     */
    private function applyCommand($command): CommandResult
    {
        switch (get_class($command)) {
            case ChangeNodeAggregateName::class:
                return $this->nodeAggregateCommandHandler->handleChangeNodeAggregateName($command);
                break;
            case CreateNodeAggregateWithNode::class:
                return $this->nodeAggregateCommandHandler->handleCreateNodeAggregateWithNode($command);
                break;
            case MoveNodeAggregate::class:
                return $this->nodeAggregateCommandHandler->handleMoveNodeAggregate($command);
                break;
            case SetNodeProperties::class:
                return $this->nodeAggregateCommandHandler->handleSetNodeProperties($command);
                break;
            case DisableNodeAggregate::class:
                return $this->nodeAggregateCommandHandler->handleDisableNodeAggregate($command);
                break;
            case EnableNodeAggregate::class:
                return $this->nodeAggregateCommandHandler->handleEnableNodeAggregate($command);
                break;
            case SetNodeReferences::class:
                return $this->nodeAggregateCommandHandler->handleSetNodeReferences($command);
                break;
            case RemoveNodeAggregate::class:
                return $this->nodeAggregateCommandHandler->handleRemoveNodeAggregate($command);
                break;
            default:
                throw new \Exception(sprintf('TODO: Command %s is not supported by handleRebaseWorkspace() currently... Please implement it there.', get_class($command)));
        }
    }

    /**
     * This method is like a combined Rebase and Publish!
     *
     * @param Command\PublishIndividualNodesFromWorkspace $command
     * @return CommandResult
     * @throws BaseWorkspaceDoesNotExist
     * @throws BaseWorkspaceHasBeenModifiedInTheMeantime
     * @throws ContentStreamAlreadyExists
     * @throws ContentStreamDoesNotExistYet
     * @throws WorkspaceDoesNotExist
     * @throws \Exception
     */
    public function handlePublishIndividualNodesFromWorkspace(Command\PublishIndividualNodesFromWorkspace $command)
    {
        $this->readSideMemoryCacheManager->disableCache();

        $workspace = $this->workspaceFinder->findOneByName($command->getWorkspaceName());
        if ($workspace === null) {
            throw new WorkspaceDoesNotExist(sprintf('The source workspace %s does not exist', $command->getWorkspaceName()), 1513924741);
        }
        $baseWorkspace = $this->workspaceFinder->findOneByName($workspace->getBaseWorkspaceName());

        if ($baseWorkspace === null) {
            throw new BaseWorkspaceDoesNotExist(sprintf('The workspace %s (base workspace of %s) does not exist', $command->getBaseWorkspaceName(), $command->getWorkspaceName()), 1513924882);
        }

        // 1) separate commands in two halves - the ones MATCHING the nodes from the command, and the REST
        $workspaceContentStreamName = ContentStreamEventStreamName::fromContentStreamIdentifier($workspace->getCurrentContentStreamIdentifier());

        $originalCommands = $this->extractCommandsFromContentStreamMetadata($workspaceContentStreamName);
        /** @var CopyableAcrossContentStreamsInterface[] $matchingCommands */
        $matchingCommands = [];
        /** @var CopyableAcrossContentStreamsInterface[] $remainingCommands */
        $remainingCommands = [];

        foreach ($originalCommands as $originalCommand) {
            // TODO: the Node Address Bounded Context MUST be moved to the CR core. This is the smoking gun why we need this ;)
            if ($this->commandMatchesNodeAddresses($originalCommand, $command->getNodeAddresses())) {
                $matchingCommands[] = $originalCommand;
            } else {
                $remainingCommands[] = $originalCommand;
            }
        }

        // 2) fork a new contentStream, based on the base WS, and apply MATCHING
        $matchingContentStream = ContentStreamIdentifier::create();
        $this->contentStreamCommandHandler->handleForkContentStream(
            new ForkContentStream(
                $matchingContentStream,
                $baseWorkspace->getCurrentContentStreamIdentifier()
            )
        )->blockUntilProjectionsAreUpToDate();

        foreach ($matchingCommands as $matchingCommand) {
            $this->applyCommand($matchingCommand->createCopyForContentStream($matchingContentStream))->blockUntilProjectionsAreUpToDate();
        }

        // 3) fork a new contentStream, based on the matching content stream, and apply REST
        $remainingContentStream = ContentStreamIdentifier::create();
        $this->contentStreamCommandHandler->handleForkContentStream(
            new ForkContentStream(
                $remainingContentStream,
                $matchingContentStream
            )
        )->blockUntilProjectionsAreUpToDate();

        foreach ($remainingCommands as $remainingCommand) {
            $this->applyCommand($remainingCommand->createCopyForContentStream($remainingContentStream))->blockUntilProjectionsAreUpToDate();
        }

        // 4) if that all worked out, take EVENTS(MATCHING) and apply them to base WS.
        $commandResult = $this->publishContentStream($matchingContentStream, $baseWorkspace->getCurrentContentStreamIdentifier());

        // 5) TODO Re-target base workspace

        // 6) switch content stream to forked WS.
        // if we got so far without an Exception, we can switch the Workspace's active Content stream.
        $streamName = StreamName::fromString('Neos.ContentRepository:Workspace:' . $command->getWorkspaceName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new WorkspaceWasRebased(
                    $command->getWorkspaceName(),
                    $remainingContentStream
                )
            )
        );
        // if we got so far without an Exception, we can switch the Workspace's active Content stream.
        $eventStore->commit($streamName, $events);

        // It is safe to only return the last command result, as the commands which were rebased are already executed "synchronously"
        return $commandResult->merge(CommandResult::fromPublishedEvents($events));
    }

    /**
     * @param object $command
     * @param \Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress[] $nodeAddresses
     * @return bool
     * @throws \Exception
     */
    private function commandMatchesNodeAddresses(object $command, array $nodeAddresses): bool
    {
        if (!$command instanceof MatchableWithNodeAddressInterface) {
            throw new \Exception(sprintf('Command %s needs to implement MatchableWithNodeAddressInterface in order to be published individually.', get_class($command)));
        }

        foreach ($nodeAddresses as $nodeAddress) {
            if ($command->matchesNodeAddress($nodeAddress)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Command\DiscardWorkspace $command
     * @return CommandResult
     * @throws ContentStreamAlreadyExists
     * @throws ContentStreamDoesNotExistYet
     */
    public function handleDiscardWorkspace(Command\DiscardWorkspace $command): CommandResult
    {
        $workspace = $this->workspaceFinder->findOneByName($command->getWorkspaceName());
        $baseWorkspace = $this->workspaceFinder->findOneByName($workspace->getBaseWorkspaceName());


        $newContentStream = ContentStreamIdentifier::create();
        $this->contentStreamCommandHandler->handleForkContentStream(
            new ForkContentStream(
                $newContentStream,
                $baseWorkspace->getCurrentContentStreamIdentifier()
            )
        )->blockUntilProjectionsAreUpToDate();

        // TODO: "Rebased" is not the correct wording here!
        $streamName = StreamName::fromString('Neos.ContentRepository:Workspace:' . $command->getWorkspaceName());
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $events = DomainEvents::withSingleEvent(
            EventWithIdentifier::create(
                new WorkspaceWasRebased(
                    $command->getWorkspaceName(),
                    $newContentStream
                )
            )
        );

        // if we got so far without an Exception, we can switch the Workspace's active Content stream.
        $eventStore->commit($streamName, $events);

        // It is safe to only return the last command result, as the commands which were rebased are already executed "synchronously"
        return CommandResult::fromPublishedEvents($events);
    }
}
