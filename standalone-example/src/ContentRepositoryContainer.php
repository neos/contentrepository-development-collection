<?php

namespace Neos\StandaloneCrExample;


use Doctrine\DBAL\Connection;
use Neos\Cache\Backend\NullBackend;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository\ContentGraph;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository\NodeFactory;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository\ProjectionContentGraph;
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
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceProjector;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventListener\EventListenerInvoker;
use Neos\EventSourcing\EventListener\EventListenerLocator;
use Neos\EventSourcing\EventStore\EventListenerTrigger\EventListenerTrigger;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\EventStoreFactory;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\EventSourcing\EventStore\Storage\EventStorageInterface;
use Neos\Flow\Configuration\ConfigurationManager;
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
            ->addArgument(new Reference(ConnectionFactory::class))
            ->addArgument(new Reference(EventNormalizer::class));

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
            ->setPublic(true)
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

        $containerBuilder->register(NodeTypeManager::class, NodeTypeManager::class)
            ->setFactory([ContentRepositoryFactories::class, 'buildNodeTypeManager'])
            ->addArgument(new Reference('nodeTypeManagerCache'))
            ->addArgument(new Reference(ConfigurationManager::class));

        $containerBuilder->register(ConfigurationManager::class, SlimConfigurationManager::class)
            ->addArgument([
                'Neos.ContentRepository:Root' => [

                ],
                'Example:Foo' => []
            ]);

        $containerBuilder->register('nodeTypeManagerCache', VariableFrontend::class)
            ->addArgument('nodeTypeManagerCache')
            ->addArgument(new Reference(NullBackend::class));
        $containerBuilder->register(NullBackend::class, NullBackend::class);

        $containerBuilder->register(ContentDimensionZookeeper::class, ContentDimensionZookeeper::class)
            ->addArgument(new Reference(ContentDimensionSourceInterface::class));
        $containerBuilder->register(ContentGraphInterface::class, ContentGraph::class)
            ->addArgument(new Reference(DbalClient::class))
            ->addArgument(new Reference(NodeFactory::class));
        $containerBuilder->register(NodeFactory::class, NodeFactory::class)
            ->addArgument(new Reference(NodeTypeManager::class));
        ;
        $containerBuilder->register(InterDimensionalVariationGraph::class, InterDimensionalVariationGraph::class)
            ->addArgument(new Reference(ContentDimensionSourceInterface::class))
            ->addArgument(new Reference(ContentDimensionZookeeper::class));
        $containerBuilder->register(NodeAggregateEventPublisher::class, NodeAggregateEventPublisher::class)
            ->addArgument(new Reference(EventStore::class));

        $containerBuilder->register(ContentDimensionSourceInterface::class, ConfigurationBasedContentDimensionSource::class)
            // TODO: add content dimension configuration here
            ->addArgument([]);

        $containerBuilder->register(GraphProjector::class, GraphProjector::class)
            ->addArgument(new Reference(ProjectionContentGraph::class))
            ->addArgument(new Reference(DbalClient::class))
            ->setPublic(true);

        $containerBuilder->register(ProjectionContentGraph::class, ProjectionContentGraph::class)
            ->addArgument(new Reference(DbalClient::class));

        $containerBuilder->register(WorkspaceProjector::class, WorkspaceProjector::class)
            ->setFactory([ContentRepositoryFactories::class, 'buildWorkspaceProjector'])
            ->addArgument(new Reference(ConnectionFactory::class))
            ->setPublic(true);

        $containerBuilder->register(EventListenerInvoker::class, SlimEventListenerInvoker::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventListenerInvoker'])
            ->addArgument(new Reference(EventStore::class))
            ->addArgument(new Reference(ConnectionFactory::class))
            ->setPublic(true);
    }

    public function getEventStore(): EventStore
    {
        return $this->container->get(EventStore::class);
    }

    public function getWorkspaceCommandHandler(): WorkspaceCommandHandler
    {
        return $this->container->get(WorkspaceCommandHandler::class);
    }

    public function getGraphProjector(): GraphProjector
    {
        return $this->container->get(GraphProjector::class);
    }

    public function getWorkspaceProjector(): WorkspaceProjector
    {
        return $this->container->get(WorkspaceProjector::class);
    }

    public function getEventListenerInvoker(): EventListenerInvoker
    {
        return $this->container->get(EventListenerInvoker::class);
    }

    public function getNodeAggregateCommandHandler(): NodeAggregateCommandHandler
    {
        return $this->container->get(NodeAggregateCommandHandler::class);
    }
}
