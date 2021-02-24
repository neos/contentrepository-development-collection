<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;

/**
 * NodeAggregateIdentifier -> Node cache
 *
 * also contains a *blacklist* of unknown NodeAggregateIdentifiers.
 */
final class NodeByNodeAggregateIdentifierCache
{
    protected $nodes = [];
    protected $nonExistingNodeAggregateIdentifiers = [];

    /**
     * @var bool
     */
    protected $isEnabled;

    public function __construct(bool $isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * basically like "contains"
     */
    public function knowsAbout(NodeAggregateIdentifier $nodeAggregateIdentifier): bool
    {
        if ($this->isEnabled === false) {
            return false;
        }

        $key = (string)$nodeAggregateIdentifier;
        return isset($this->nodes[$key]) || isset($this->nonExistingNodeAggregateIdentifiers[$key]);
    }

    public function add(NodeAggregateIdentifier $nodeAggregateIdentifier, TraversableNodeInterface $node): void
    {
        if ($this->isEnabled === false) {
            return;
        }

        $key = (string)$nodeAggregateIdentifier;
        $this->nodes[$key] = $node;
    }

    public function rememberNonExistingNodeAggregateIdentifier(NodeAggregateIdentifier $nodeAggregateIdentifier): void
    {
        if ($this->isEnabled === false) {
            return;
        }

        $key = (string)$nodeAggregateIdentifier;
        $this->nonExistingNodeAggregateIdentifiers[$key] = true;
    }

    public function get(NodeAggregateIdentifier $nodeAggregateIdentifier): ?TraversableNodeInterface
    {
        if ($this->isEnabled === false) {
            return null;
        }

        $key = (string)$nodeAggregateIdentifier;
        return $this->nodes[$key] ?? null;
    }
}
