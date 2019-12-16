<?php


use Neos\EventSourcedContentRepository\Standalone\Configuration\ContentRepositoryConfiguration;
use Neos\EventSourcedContentRepository\Standalone\Configuration\DatabaseConnectionParams;
use Neos\EventSourcedContentRepository\Standalone\Configuration\DimensionConfiguration;
use Neos\EventSourcedContentRepository\Standalone\Configuration\NodeTypesConfiguration;
use Neos\StandaloneCrExample\Example1;

require __DIR__ . '/vendor/autoload.php';

// Adjust DB credentials as needed
$contentRepositoryConfiguration = ContentRepositoryConfiguration::create(
    __DIR__ . '/vendor',
    DatabaseConnectionParams::create()
        ->mysqlDriver()
        ->host('localhost')
        ->user('root')
        ->password('')
        ->dbname('escr-standalone'),
    NodeTypesConfiguration::create()
        ->add([
            'Example:Foo' => []
        ]),
    DimensionConfiguration::createEmpty()
);

Example1::run($contentRepositoryConfiguration);
