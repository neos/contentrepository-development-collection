<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient;

/*
 * This file is part of the Neos.ContentGraph.RedisGraphAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Neos\ContentGraph\RedisGraphAdapter\Domain\Projection\NodeRelationAnchorPoint;
use Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient\Graph\Graph;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient;
use Neos\EventSourcedContentRepository\Domain;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeAggregate;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;

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

    public function graphQuery(ContentStreamIdentifier $contentStreamIdentifier, string $query) {
        $graphName = $contentStreamIdentifier->jsonSerialize();
        $graph = new Graph($graphName, $this->redis);
        $graph->addNode()
        $graph->commit();
        $response = $this->getRedisClient()->rawCommand(
            'GRAPH.QUERY',
            [$graphName, $query]
        );
        return $response;
    }

    public function transactionalForContentStream(ContentStreamIdentifier $contentStreamIdentifier, \Closure $callback): void
    {
        $graphName = $contentStreamIdentifier->jsonSerialize();
        $graph = new Graph($graphName, $this->redis);
        $callback($graph);
        $graph->commit();
    }
}
