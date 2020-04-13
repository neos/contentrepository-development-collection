<?php


namespace Neos\ContentGraph\RedisGraphAdapter\Command;


use Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\GraphDumper;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\Flow\Annotations as Flow;
use Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient;
use Neos\Flow\Cli\CommandController;

class RedisGraphCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var RedisClient
     */
    protected $redisClient;

    public function dumpCommand()
    {
        $keys = $this->redisClient->getRedisClient()->keys('contentStream:*');
        foreach ($keys as $key) {
            $contentStreamIdentifierString = substr($key, strlen('contentStream:'));
            $contentStreamIdentifier = ContentStreamIdentifier::fromString($contentStreamIdentifierString);
            $graph = $this->redisClient->getGraphForReading($contentStreamIdentifier);

            echo GraphDumper::render($graph);
        }

        var_dump("Called");
    }

}
