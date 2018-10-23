<?php

namespace Neos\EventSourcedContentRepository\Domain\Projection\Content;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache\AllChildNodesByNodeIdentifierCache;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache\NamedChildNodeByNodeIdentifierCache;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache\NodeByNodeAggregateIdentifierCache;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache\NodeByNodeIdentifierCache;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache\NodePathCache;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\InMemoryCache\ParentNodeIdentifierByChildNodeIdentifierCache;

/**
 * Accessors to In Memory Cache.
 */
final class InMemoryCache
{
    /**
     * @var NodePathCache
     */
    private $nodePathCache;

    /**
     * @var NodeByNodeIdentifierCache
     */
    private $nodeByNodeIdentifierCache;

    /**
     * @var NodeByNodeAggregateIdentifierCache
     */
    private $nodeByNodeAggregateIdentifierCache;

    /**
     * @var AllChildNodesByNodeIdentifierCache
     */
    private $allChildNodesByNodeIdentifierCache;

    /**
     * @var NamedChildNodeByNodeIdentifierCache
     */
    private $namedChildNodeByNodeIdentifierCache;

    /**
     * @var ParentNodeIdentifierByChildNodeIdentifierCache
     */
    private $parentNodeIdentifierByChildNodeIdentifierCache;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * @return NodePathCache
     */
    public function getNodePathCache(): NodePathCache
    {
        return $this->nodePathCache;
    }

    /**
     * @return NodeByNodeIdentifierCache
     */
    public function getNodeByNodeIdentifierCache(): NodeByNodeIdentifierCache
    {
        return $this->nodeByNodeIdentifierCache;
    }

    public function getNodeByNodeAggregateIdentifierCache(): NodeByNodeAggregateIdentifierCache
    {
        return $this->nodeByNodeAggregateIdentifierCache;
    }

    /**
     * @return AllChildNodesByNodeIdentifierCache
     */
    public function getAllChildNodesByNodeIdentifierCache(): AllChildNodesByNodeIdentifierCache
    {
        return $this->allChildNodesByNodeIdentifierCache;
    }

    /**
     * @return NamedChildNodeByNodeIdentifierCache
     */
    public function getNamedChildNodeByNodeIdentifierCache(): NamedChildNodeByNodeIdentifierCache
    {
        return $this->namedChildNodeByNodeIdentifierCache;
    }

    /**
     * @return ParentNodeIdentifierByChildNodeIdentifierCache
     */
    public function getParentNodeIdentifierByChildNodeIdentifierCache(): ParentNodeIdentifierByChildNodeIdentifierCache
    {
        return $this->parentNodeIdentifierByChildNodeIdentifierCache;
    }

    public function reset()
    {
        $this->nodePathCache = new NodePathCache();
        $this->nodeByNodeIdentifierCache = new NodeByNodeIdentifierCache();
        $this->nodeByNodeAggregateIdentifierCache = new NodeByNodeAggregateIdentifierCache();
        $this->allChildNodesByNodeIdentifierCache = new AllChildNodesByNodeIdentifierCache();
        $this->namedChildNodeByNodeIdentifierCache = new NamedChildNodeByNodeIdentifierCache();
        $this->parentNodeIdentifierByChildNodeIdentifierCache = new ParentNodeIdentifierByChildNodeIdentifierCache();
    }
}
