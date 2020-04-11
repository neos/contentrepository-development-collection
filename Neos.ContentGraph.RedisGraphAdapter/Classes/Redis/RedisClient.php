<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Redis;

/*
 * This file is part of the Neos.ContentGraph.RedisGraphAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\Graph;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The Doctrine DBAL adapter content graph
 *
 * To be used as a read-only source of nodes
 *
 * @Flow\Scope("singleton")
 * @api
 */
final class RedisClient
{
    /**
     * @var \Redis
     */
    private $redis;

    public function getRedisClient()
    {
        if ($this->redis) {
            $this->redis = new \Redis();
            $this->redis->pconnect('127.0.0.1', 6379);
        }

        return $this->redis;
    }

    public function transactionalForContentStream(ContentStreamIdentifier $contentStreamIdentifier, \Closure $callback): void
    {
        $graphName = $contentStreamIdentifier->jsonSerialize();
        $graph = new Graph($graphName, $this->redis);

        $this->redis->multi();
        try {
            $callback($graph, $this->redis);
        } catch(\Throwable $e) {
            $this->redis->discard();
            throw $e;
        }
        $this->redis->exec();
    }
}
