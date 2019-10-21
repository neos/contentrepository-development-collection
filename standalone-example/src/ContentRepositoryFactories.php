<?php

namespace Neos\StandaloneCrExample;


use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
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


}
