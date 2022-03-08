<?php

/*
 * This file is part of the Neos.ContentGraph.PostgreSQLAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query;

use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;

/**
 * A cypher node label, represented by ":`Acme.Site:Document`" in a cypher pattern
 */
#[Flow\Proxy(false)]
final class CypherNodeLabel implements \Stringable
{
    private function __construct(
        public readonly string $value
    ) {
    }

    public static function fromString(string $string): self
    {
        return new self($string);
    }

    public static function fromNodeTypeName(NodeTypeName $nodeTypeName): self
    {
        return new self((string)$nodeTypeName);
    }

    public function toNodeTypeName(): NodeTypeName
    {
        return NodeTypeName::fromString($this->value);
    }

    public function __toString(): string
    {
        return ':' . $this->value;
    }
}
