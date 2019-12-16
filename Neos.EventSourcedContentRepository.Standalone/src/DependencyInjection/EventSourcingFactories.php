<?php

namespace Neos\EventSourcedContentRepository\Standalone\DependencyInjection;

use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceProjector;
use Neos\EventSourcedContentRepository\Standalone\Configuration\ContentRepositoryConfiguration;
use Neos\EventSourcedContentRepository\Standalone\DependencyInjection\Overrides\SlimEventListenerInvoker;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventListener\EventListenerLocator;
use Neos\EventSourcing\EventStore\EventListenerTrigger\EventListenerTrigger;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\Storage\Doctrine\DoctrineEventStorage;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\EventSourcing\EventStore\Storage\EventStorageInterface;
use Neos\Utility\ObjectAccess;

class EventSourcingFactories
{
    public static function buildConnectionFactory(): ConnectionFactory
    {
        $connectionFactory = new ConnectionFactory();
        ObjectAccess::setProperty($connectionFactory, 'defaultFlowDatabaseConfiguration', [], true);
        return $connectionFactory;
    }

    public static function buildEventStorage(ConnectionFactory $connectionFactory, EventNormalizer $eventNormalizer, ContentRepositoryConfiguration $contentRepositoryConfiguration): DoctrineEventStorage
    {
        $storage = new DoctrineEventStorage([
            'backendOptions' => $contentRepositoryConfiguration->getDatabaseConnectionParams()->getParams(),
            'mappingTypes' => [
                'flow_json_array' => [
                    'dbType' => 'json_array',
                    'className' => 'Neos\Flow\Persistence\Doctrine\DataTypes\JsonArrayType'
                ]
            ]
        ], $eventNormalizer);

        ObjectAccess::setProperty($storage, 'connectionFactory', $connectionFactory, true);
        ObjectAccess::setProperty($storage, 'now', new \DateTimeImmutable(), true);
        $storage->initializeObject();

        return $storage;
    }

    public static function buildEventStore(EventStorageInterface $eventStorage, EventTypeResolver $eventTypeResolver, EventNormalizer $eventNormalizer, EventListenerTrigger $eventListenerTrigger): EventStore
    {
        $eventStore = new EventStore($eventStorage);
        ObjectAccess::setProperty($eventStore, 'eventTypeResolver', $eventTypeResolver, true);
        ObjectAccess::setProperty($eventStore, 'eventNormalizer', $eventNormalizer, true);
        ObjectAccess::setProperty($eventStore, 'eventListenerTrigger', $eventListenerTrigger, true);
        return $eventStore;
    }

    public static function buildEventNormalizer(EventTypeResolver $eventTypeResolver): EventNormalizer
    {
        $eventNormalizer = new EventNormalizer();
        ObjectAccess::setProperty($eventNormalizer, 'eventTypeResolver', $eventTypeResolver, true);

        return $eventNormalizer;
    }

    public static function buildEventListenerTrigger(EventListenerLocator $eventListenerLocator): EventListenerTrigger
    {
        $eventListenerTrigger = new EventListenerTrigger();
        ObjectAccess::setProperty($eventListenerTrigger, 'eventListenerLocator', $eventListenerLocator, true);
        return $eventListenerTrigger;
    }

    public static function buildEventListenerLocator(): EventListenerLocator
    {
        $eventListenerLocator = unserialize('O:' . strlen(EventListenerLocator::class) . ':"' . EventListenerLocator::class . '":0:{};');

        // array in the format ['<eventClassName>' => ['<listenerClassName>' => '<listenerMethodName>', '<listenerClassName2>' => '<listenerMethodName2>', ...]]
        $eventClassNamesAndListeners = [];

        $listenerClassNames = [
            GraphProjector::class,
            WorkspaceProjector::class
        ];

        foreach ($listenerClassNames as $listenerClassName) {
            $methods = get_class_methods($listenerClassName);
            foreach ($methods as $listenerMethodName) {
                if (strpos($listenerMethodName, 'when') === 0) {
                    // method starts with "when"

                    $listenerMethod = new \ReflectionMethod($listenerClassName, $listenerMethodName);
                    $params = $listenerMethod->getParameters();
                    $eventClassName = $params[0]->getType()->getName();

                    $eventClassNamesAndListeners[$eventClassName][$listenerClassName] = $listenerMethodName;
                }
            }
        }

        ObjectAccess::setProperty($eventListenerLocator, 'eventClassNamesAndListeners', $eventClassNamesAndListeners, true);

        return $eventListenerLocator;
    }

    public static function buildEventListenerInvoker(EventStore $eventStore, ConnectionFactory $connectionFactory, ContentRepositoryConfiguration $contentRepositoryConfiguration)
    {
        $connection = $connectionFactory->create([
            'backendOptions' => $contentRepositoryConfiguration->getDatabaseConnectionParams()->getParams()
        ]);
        return new SlimEventListenerInvoker($eventStore, $connection);
    }
}
