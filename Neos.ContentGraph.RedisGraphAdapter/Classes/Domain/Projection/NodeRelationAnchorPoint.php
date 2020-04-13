<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Domain\Projection;

/*
 * This file is part of the Neos.ContentGraph.RedisGraphAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;

/**
 * The node relation anchor value object
 *
 * @Flow\Proxy(false)
 */
class NodeRelationAnchorPoint implements \JsonSerializable
{
    /**
     * @var string
     */
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function create(): self
    {
        return new static(Algorithms::generateUUID());
    }

    public static function forRootEdge(): self
    {
        return new static('00000000-0000-0000-0000-000000000000');
    }

    public static function fromString(string $value): self
    {
        return new static($value);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
