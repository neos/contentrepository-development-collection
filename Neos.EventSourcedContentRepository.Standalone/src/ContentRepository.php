<?php

namespace Neos\EventSourcedContentRepository\Standalone;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Migration;
use Doctrine\DBAL\Types\Type;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceProjector;
use Neos\EventSourcedContentRepository\Standalone\Configuration\ContentRepositoryConfiguration;
use Neos\EventSourcedContentRepository\Standalone\DependencyInjection\ContentRepositoryContainerConfigurer;
use Neos\EventSourcedContentRepository\Standalone\DependencyInjection\EventSourcingContainerConfigurer;
use Neos\EventSourcing\EventListener\EventListenerInvoker;
use Neos\EventSourcing\EventStore\EventStore;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ContentRepository
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var ContentRepositoryConfiguration
     */
    private $contentRepositoryConfiguration;

    private function __construct(ContentRepositoryConfiguration $contentRepositoryConfiguration)
    {
        $this->contentRepositoryConfiguration = $contentRepositoryConfiguration;

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('contentRepositoryConfiguration', $contentRepositoryConfiguration);

        EventSourcingContainerConfigurer::configure($containerBuilder, $contentRepositoryConfiguration);
        ContentRepositoryContainerConfigurer::configure($containerBuilder, $contentRepositoryConfiguration);
        $containerBuilder->compile();
        $this->container = $containerBuilder;

        // WORKAROUND: Fetch the event store first, so that its Doctrine types are registered.
        $this->container->get(EventStore::class);
    }

    public static function create(ContentRepositoryConfiguration $contentRepositoryConfiguration): self
    {
        return new ContentRepository($contentRepositoryConfiguration);
    }

    public function truncateAllTables(): void
    {
        $connection = $this->buildDatabaseConnection();
        foreach ($connection->getSchemaManager()->listTables() as $table) {
            $truncateTableSql = $connection->getDriver()->getDatabasePlatform()->getDropTableSQL($table->getName());

            $connection->beginTransaction();
            $connection->exec($truncateTableSql);
            $connection->commit();
        }
    }

    private function buildDatabaseConnection(): Connection
    {
        $connection = DriverManager::getConnection($this->contentRepositoryConfiguration->getDatabaseConnectionParams()->getParams());
        if (!Type::hasType('flow_json_array')) {
            Type::addType('flow_json_array', 'Neos\Flow\Persistence\Doctrine\DataTypes\JsonArrayType');
        }
        $connection->getDatabasePlatform()->registerDoctrineTypeMapping('json_array', 'flow_json_array');

        return $connection;
    }

    public function migrate()
    {
        $configuration = new Configuration($this->buildDatabaseConnection(), null);
        $configuration->setMigrationsNamespace('Neos\Flow\Persistence\Doctrine\Migrations');
        $configuration->setMigrationsDirectory(__DIR__ . '_genMigrations');
        $configuration->setMigrationsTableName('neos_contentrepository_dbmigrations');

        $configuration->createMigrationTable();

        $configuration->registerMigrationsFromDirectory($this->contentRepositoryConfiguration->getVendorDirectory() . '/neos/event-sourced-content-repository/Migrations/Mysql');
        $configuration->registerMigrationsFromDirectory($this->contentRepositoryConfiguration->getVendorDirectory() . '/neos/event-sourcing/Migrations/Mysql');
        $configuration->registerMigrationsFromDirectory($this->contentRepositoryConfiguration->getVendorDirectory() . '/neos/contentgraph-doctrinedbaladapter/Migrations/Mysql');

        $migration = new Migration($configuration);
        $migration->migrate();
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

    public function runSynchronously(): void
    {
        $graphProjector = $this->getGraphProjector();
        $workspaceProjector = $this->getWorkspaceProjector();

        $graphProjector->assumeProjectorRunsSynchronously();
        $workspaceProjector->assumeProjectorRunsSynchronously();

        $eventListenerInvoker = $this->getEventListenerInvoker();
        $this->getEventStore()->onPostCommit(function () use($eventListenerInvoker, $graphProjector, $workspaceProjector) {
            $eventListenerInvoker->catchUp($graphProjector);
            $eventListenerInvoker->catchUp($workspaceProjector);
        });
    }
}
