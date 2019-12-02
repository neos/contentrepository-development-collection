<?php

namespace Neos\EventSourcedContentRepository\Standalone\DependencyInjection;


use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceProjector;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
use Neos\EventSourcedContentRepository\Standalone\Configuration\ContentRepositoryConfiguration;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Utility\ObjectAccess;

class ContentRepositoryFactories
{

    public static function buildDbalClient(ContentRepositoryConfiguration $contentRepositoryConfiguration): DbalClient
    {
        $dbalClient = new DbalClient();

        $connection = DriverManager::getConnection($contentRepositoryConfiguration->getDatabaseConnectionParams()->getParams(), new Configuration());

        ObjectAccess::setProperty($dbalClient, 'connection', $connection, true);
        return $dbalClient;
    }

    public static function buildWorkspaceProjector(ConnectionFactory $connectionFactory, ContentRepositoryConfiguration $contentRepositoryConfiguration)
    {
        $connection = $connectionFactory->create([
            'backendOptions' => $contentRepositoryConfiguration->getDatabaseConnectionParams()->getParams()
        ]);
        return new WorkspaceProjector($connection);
    }


    public static function buildNodeTypeManager(VariableFrontend $fullConfigurationCache, ConfigurationManager $configurationManager) {
        $nodeTypeManager = new NodeTypeManager();
        ObjectAccess::setProperty($nodeTypeManager, 'fullConfigurationCache', $fullConfigurationCache, true);
        ObjectAccess::setProperty($nodeTypeManager, 'configurationManager', $configurationManager, true);

        return $nodeTypeManager;
    }
}
