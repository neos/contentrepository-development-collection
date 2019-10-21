<?php

namespace Neos\StandaloneCrExample;


use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository\ContentGraph;
use Neos\ContentRepository\DimensionSpace\Dimension\ConfigurationBasedContentDimensionSource;
use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimensionSourceInterface;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\ContentDimensionZookeeper;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\InterDimensionalVariationGraph;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamRepository;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
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
        $containerBuilder->compile();
        $this->container = $containerBuilder;
    }

    private static function configureEventSourcing(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(EventStore::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventStore'])
            ->addArgument(new Reference(EventStorageInterface::class))
            ->addArgument(new Reference(EventTypeResolver::class))
            ->addArgument(new Reference(EventNormalizer::class))
            ->addArgument(new Reference(EventListenerTrigger::class))
            ->setPublic(true);

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
        $containerBuilder->register(WorkspaceCommandHandler::class, WorkspaceCommandHandler::class)
            ->setPublic(true)
            ->addArgument(new Reference(EventStore::class))
            ->addArgument(new Reference(WorkspaceFinder::class))
            ->addArgument(new Reference(NodeAggregateCommandHandler::class))
            ->addArgument(new Reference(ContentStreamCommandHandler::class))
            ->addArgument(new Reference(ReadSideMemoryCacheManager::class))
            ->addArgument(new Reference(ContentGraphInterface::class));

        $containerBuilder->register(WorkspaceFinder::class, WorkspaceFinder::class)
            ->addArgument(new Reference(DbalClient::class));

        $containerBuilder->register(DbalClient::class, DbalClient::class)
            ->setFactory([ContentRepositoryFactories::class, 'buildDbalClient'])
            ->addArgument(new Reference(EventTypeResolver::class));;

        $containerBuilder->register(NodeAggregateCommandHandler::class, NodeAggregateCommandHandler::class)
            ->addArgument(new Reference(ContentStreamRepository::class))
            ->addArgument(new Reference(NodeTypeManager::class))
            ->addArgument(new Reference(ContentDimensionZookeeper::class))
            ->addArgument(new Reference(ContentGraphInterface::class))
            ->addArgument(new Reference(InterDimensionalVariationGraph::class))
            ->addArgument(new Reference(NodeAggregateEventPublisher::class))
            ->addArgument(new Reference(ReadSideMemoryCacheManager::class));

        $containerBuilder->register(ContentStreamCommandHandler::class, ContentStreamCommandHandler::class)
            ->addArgument(new Reference(ContentStreamRepository::class))
            ->addArgument(new Reference(EventStore::class))
            ->addArgument(new Reference(ReadSideMemoryCacheManager::class));

        $containerBuilder->register(ReadSideMemoryCacheManager::class, ReadSideMemoryCacheManager::class)
            ->addArgument(new Reference(ContentGraphInterface::class))
            ->addArgument(new Reference(WorkspaceFinder::class));
        $containerBuilder->register(ContentStreamRepository::class, ContentStreamRepository::class)
            ->addArgument(new Reference(EventStore::class));

        $containerBuilder->register(NodeTypeManager::class, NodeTypeManager::class);
        $containerBuilder->register(ContentDimensionZookeeper::class, ContentDimensionZookeeper::class)
            ->addArgument(new Reference(ContentDimensionSourceInterface::class));
        $containerBuilder->register(ContentGraphInterface::class, ContentGraph::class);
        $containerBuilder->register(InterDimensionalVariationGraph::class, InterDimensionalVariationGraph::class)
            ->addArgument(new Reference(ContentDimensionSourceInterface::class))
            ->addArgument(new Reference(ContentDimensionZookeeper::class));
        $containerBuilder->register(NodeAggregateEventPublisher::class, NodeAggregateEventPublisher::class)
            ->addArgument(new Reference(EventStore::class));

        $containerBuilder->register(ContentDimensionSourceInterface::class, ConfigurationBasedContentDimensionSource::class)
            // TODO: add content dimension configuration here
            ->addArgument([]);
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
