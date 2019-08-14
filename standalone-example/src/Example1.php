<?php

namespace Neos\StandaloneCrExample;


use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStream;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Event\ContentStreamWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventListener\EventListenerLocator;
use Neos\EventSourcing\EventStore\EventListenerTrigger\EventListenerTrigger;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreManager;
use Neos\EventSourcing\EventStore\Storage\Doctrine\DoctrineEventStorage;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Utility\ObjectAccess;

class Example1
{

    private static function prepareEventStorage(): DoctrineEventStorage
    {
        $storage = new DoctrineEventStorage([
            'backendOptions' => [
                'driver' => 'pdo_mysql',
                'dbname' => 'escr-standalone',
                'user' => 'root',
                'password' => '',
                'host' => 'localhost',
            ],
            'mappingTypes' => [
                'flow_json_array' => [
                    'dbType' => 'json_array',
                    'className' => 'Neos\Flow\Persistence\Doctrine\DataTypes\JsonArrayType'
                ]
            ]
        ]);
        $connectionFactory = new ConnectionFactory();
        ObjectAccess::setProperty($connectionFactory, 'defaultFlowDatabaseConfiguration', [], true);

        // HACKS
        ObjectAccess::setProperty($storage, 'connectionFactory', $connectionFactory, TRUE);
        ObjectAccess::setProperty($storage, 'now', new \DateTimeImmutable(), TRUE);
        $storage->initializeObject();

        return $storage;
    }

    private static function prepareEventStore(): EventStore
    {
        $eventStore = new EventStore(self::prepareEventStorage());
        $eventTypeResolver = new SlimEventTypeResolver();
        ObjectAccess::setProperty($eventStore, 'eventTypeResolver', $eventTypeResolver, true);

        $eventNormalizer = new EventNormalizer();
        ObjectAccess::setProperty($eventNormalizer, 'eventTypeResolver', $eventTypeResolver, true);
        $initializeObjectOfNormalizer = new \ReflectionMethod($eventNormalizer, 'initializeObject');
        $initializeObjectOfNormalizer->setAccessible(true);
        $initializeObjectOfNormalizer->invoke($eventNormalizer);
        ObjectAccess::setProperty($eventStore, 'eventNormalizer', $eventNormalizer, true);

        $eventListenerTrigger = new EventListenerTrigger();
        ObjectAccess::setProperty($eventListenerTrigger, 'eventListenerLocator', self::prepareEventListenerLocator(), true);
        ObjectAccess::setProperty($eventStore, 'eventListenerTrigger', $eventListenerTrigger, true);
        return $eventStore;
    }

    private static function prepareEventListenerLocator(): EventListenerLocator
    {
        $eventListenerLocator = unserialize('O:' . strlen(EventListenerLocator::class) . ':"' . EventListenerLocator::class . '":0:{};');

        // array in the format ['<eventClassName>' => ['<listenerClassName>' => '<listenerMethodName>', '<listenerClassName2>' => '<listenerMethodName2>', ...]]
        $eventClassNamesAndListeners = [];

        $listenerClassNames = [
            GraphProjector::class
        ];

        foreach ($listenerClassNames as $listenerClassName) {
            var_dump($listenerClassName);
            $methods = get_class_methods($listenerClassName);
            var_dump($methods);
            foreach ($methods as $listenerMethodName) {
                if (strpos($listenerMethodName, 'handle') === 0) {
                    // method starts with "handle"

                    $listenerMethod = new \ReflectionMethod($listenerClassName, $listenerMethodName);
                    $params = $listenerMethod->getParameters();
                    $eventClassName = $params[0]->getType()->getName();

                    $eventClassNamesAndListeners[$eventClassName][$listenerClassName] = $listenerMethodName;
                }
            }
        }

        var_dump($eventClassNamesAndListeners);


        ObjectAccess::setProperty($eventListenerLocator, 'eventClassNamesAndListeners', $eventClassNamesAndListeners, true);

        return $eventListenerLocator;
    }

    public function run()
    {
        echo "Hallo";

        $cs = ContentStreamIdentifier::create();
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($cs)->getEventStreamName();
        $event = new ContentStreamWasCreated(
            $cs,
            UserIdentifier::forSystemUser()
        );
        $event = EventWithIdentifier::create($event);
        $eventStore = self::prepareEventStore();

        $eventStore->setup();

        $eventStore->commit($streamName, DomainEvents::withSingleEvent($event));

        $command = new CreateRootWorkspace(
            WorkspaceName::forLive(),
            new WorkspaceTitle('live'),
            new WorkspaceDescription('The live WS'),
            UserIdentifier::forSystemUser(),
            $cs
        );

        //$cmd = new WorkspaceCommandHandler();
        //$cmd->handleCreateRootWorkspace($command);
    }


}
