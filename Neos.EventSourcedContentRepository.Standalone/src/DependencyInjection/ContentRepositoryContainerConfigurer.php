<?php

namespace Neos\EventSourcedContentRepository\Standalone\DependencyInjection;

use Neos\Cache\Backend\NullBackend;
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
use Neos\EventSourcedContentRepository\Standalone\Configuration\ContentRepositoryConfiguration;
use Neos\EventSourcedContentRepository\Standalone\DependencyInjection\Overrides\SlimConfigurationManager;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\Flow\Configuration\ConfigurationManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ContentRepositoryContainerConfigurer
{
    public static function configure(ContainerBuilder $containerBuilder, ContentRepositoryConfiguration $contentRepositoryConfiguration)
    {
        $containerBuilder->register(ContentRepositoryConfiguration::class, WorkspaceCommandHandler::class);

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
            ->addArgument('%contentRepositoryConfiguration%');

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
            ->addArgument($contentRepositoryConfiguration->getNodeTypes()->getConfiguration());

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
            ->addArgument($contentRepositoryConfiguration->getDimensions()->getConfiguration());

        $containerBuilder->register(GraphProjector::class, GraphProjector::class)
            ->addArgument(new Reference(ProjectionContentGraph::class))
            ->addArgument(new Reference(DbalClient::class))
            ->setPublic(true);

        $containerBuilder->register(ProjectionContentGraph::class, ProjectionContentGraph::class)
            ->addArgument(new Reference(DbalClient::class));

        $containerBuilder->register(WorkspaceProjector::class, WorkspaceProjector::class)
            ->setFactory([ContentRepositoryFactories::class, 'buildWorkspaceProjector'])
            ->addArgument(new Reference(ConnectionFactory::class))
            ->addArgument('%contentRepositoryConfiguration%')
            ->setPublic(true);
    }
}
