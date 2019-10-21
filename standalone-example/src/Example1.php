<?php

namespace Neos\StandaloneCrExample;


use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Event\ContentStreamWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventListener\EventListenerLocator;
use Neos\EventSourcing\EventStore\EventListenerTrigger\EventListenerTrigger;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\Utility\ObjectAccess;
use Ramsey\Uuid\Uuid;

class Example1
{
    public function run()
    {
        $container = new ContentRepositoryContainer();
        $eventStore = $container->getEventStore();

        $cs = ContentStreamIdentifier::create();
        /*$streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($cs)->getEventStreamName();
        $event = new ContentStreamWasCreated(
            $cs,
            UserIdentifier::forSystemUser()
        );
        $event = DecoratedEvent::addIdentifier($event, Uuid::uuid4()->toString());*/

        $eventStore->setup();

        //$eventStore->commit($streamName, DomainEvents::withSingleEvent($event));

        $command = new CreateRootWorkspace(
            WorkspaceName::forLive(),
            new WorkspaceTitle('live'),
            new WorkspaceDescription('The live WS'),
            UserIdentifier::forSystemUser(),
            $cs
        );

        $cmd = $container->getWorkspaceCommandHandler();
        $cmd->handleCreateRootWorkspace($command);
    }


}
