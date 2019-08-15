<?php

namespace Neos\StandaloneCrExample;


use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventListener\EventListenerLocator;
use Neos\EventSourcing\EventStore\EventListenerTrigger\EventListenerTrigger;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\EventSourcing\EventStore\Storage\EventStorageInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ContentRepositoryContainer
{
    /**
     * @var Container
     */
    protected $container;

    public function __construct()
    {
        $containerBuilder = new ContainerBuilder();
        self::configureEventSourcing($containerBuilder);
        self::configureContentRepository($containerBuilder);
        $this->container = $containerBuilder;
    }

    private static function configureEventSourcing(ContainerBuilder $containerBuilder): void
    {
        // Public
        $containerBuilder->register(EventStore::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventStore'])
            ->addArgument(new Reference(EventStorageInterface::class))
            ->addArgument(new Reference(EventTypeResolver::class))
            ->addArgument(new Reference(EventNormalizer::class))
            ->addArgument(new Reference(EventListenerTrigger::class));

        // Internal
        $containerBuilder
            ->register(ConnectionFactory::class)
            ->setFactory([EventSourcingFactories::class, 'buildConnectionFactory']);

        $containerBuilder->register(EventStorageInterface::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventStorage'])
            ->addArgument(new Reference(ConnectionFactory::class));

        $containerBuilder->register(EventTypeResolver::class, SlimEventTypeResolver::class);

        $containerBuilder->register(EventNormalizer::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventNormalizer'])
            ->addArgument(new Reference(EventTypeResolver::class));

        $containerBuilder->register(EventListenerTrigger::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventListenerTrigger'])
            ->addArgument(new Reference(EventListenerLocator::class));

        $containerBuilder->register(EventListenerLocator::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventListenerLocator']);
    }

    private static function configureContentRepository(ContainerBuilder $containerBuilder): void
    {
        // Public
        $containerBuilder->register(WorkspaceCommandHandler::class, WorkspaceCommandHandler::class);
    }

    public function getEventStore(): EventStore
    {
        return $this->container->get(EventStore::class);
    }

    public function getWorkspaceCommandHandler(): WorkspaceCommandHandler
    {
        return $this->container->get(WorkspaceCommandHandler::class);
    }
}
