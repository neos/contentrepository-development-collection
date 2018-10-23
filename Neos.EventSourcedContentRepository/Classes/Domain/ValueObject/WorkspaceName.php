<?php

namespace Neos\EventSourcedContentRepository\Domain\ValueObject;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Cache\CacheAwareInterface;

/**
 * Name of a workspace.
 *
 * Implements CacheAwareInterface because of Fusion Runtime caching and Routing
 */
final class WorkspaceName implements \JsonSerializable, CacheAwareInterface
{
    const WORKSPACE_NAME_LIVE = 'live';

    /**
     * @var string
     */
    private $name;

    /**
     * Name constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->setName($name);
    }

    /**
     * @param string $name
     */
    private function setName(string $name)
    {
        if (preg_match('/^[\p{L}\p{P}\d \.]{1,200}$/u', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid workspace name given.', 1505826610318);
        }
        $this->name = $name;
    }

    /**
     * @return WorkspaceName
     */
    public static function forLive(): self
    {
        return new self(self::WORKSPACE_NAME_LIVE);
    }

    /**
     * @return bool
     */
    public function isLive(): bool
    {
        return $this->name === self::WORKSPACE_NAME_LIVE;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function jsonSerialize(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCacheEntryIdentifier(): string
    {
        return $this->name;
    }
}
