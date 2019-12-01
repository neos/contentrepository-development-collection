<?php

namespace Neos\StandaloneCrExample;


use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Neos\Cache\Frontend\StringFrontend;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository\ContentGraph;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceProjector;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Utility\ObjectAccess;

class ContentRepositoryFactories
{

    public static function buildDbalClient(): DbalClient
    {
        $dbalClient = new DbalClient();

        $backendOptions = [
            'driver' => 'pdo_mysql',
            'dbname' => 'escr-standalone',
            'user' => 'root',
            'password' => '',
            'host' => 'localhost',
        ];
        $connection = DriverManager::getConnection($backendOptions, new Configuration());

        ObjectAccess::setProperty($dbalClient, 'connection', $connection, true);
        return $dbalClient;
    }

    public static function buildWorkspaceProjector(ConnectionFactory $connectionFactory)
    {
        $connection = $connectionFactory->create([
            'backendOptions' => [
                'driver' => 'pdo_mysql',
                'dbname' => 'escr-standalone',
                'user' => 'root',
                'password' => '',
                'host' => 'localhost',
            ]
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
