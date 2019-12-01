<?php

namespace Neos\StandaloneCrExample;


use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Migration;
use Doctrine\DBAL\Types\Type;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\Flow\Package\PackageInterface;
use Neos\Utility\Files;

class Migrations
{
    public function run()
    {
        $connectionParams = [
            'driver' => 'pdo_mysql',
            'dbname' => 'escr-standalone',
            'user' => 'root',
            'password' => '',
            'host' => 'localhost',
        ];
        $connection = DriverManager::getConnection($connectionParams);
        if (!Type::hasType('flow_json_array')) {
            Type::addType('flow_json_array', 'Neos\Flow\Persistence\Doctrine\DataTypes\JsonArrayType');
        }
        $connection->getDatabasePlatform()->registerDoctrineTypeMapping('json_array', 'flow_json_array');

        foreach ($connection->getSchemaManager()->listTables() as $table) {
            $truncateTableSql = $connection->getDriver()->getDatabasePlatform()->getDropTableSQL($table->getName());

            $connection->beginTransaction();
            $connection->exec($truncateTableSql);
            $connection->commit();
        }


        $configuration = new Configuration($connection, null);
        $configuration->setMigrationsNamespace('Neos\Flow\Persistence\Doctrine\Migrations');
        $configuration->setMigrationsDirectory(__DIR__ . '_genMigrations');
        $configuration->setMigrationsTableName('standalonecr_migrations');

        $configuration->createMigrationTable();

        $configuration->registerMigrationsFromDirectory(__DIR__ . '/../vendor/neos/event-sourced-content-repository/Migrations/Mysql');
        $configuration->registerMigrationsFromDirectory(__DIR__ . '/../vendor/neos/event-sourcing/Migrations/Mysql');
        $configuration->registerMigrationsFromDirectory(__DIR__ . '/../vendor/neos/contentgraph-doctrinedbaladapter/Migrations/Mysql');

        $migration = new Migration($configuration);
        $migration->migrate();

    }
}
