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

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class CypherPatternPosition implements \JsonSerializable
{
    private function __construct(
        public readonly int $index,
        public readonly int $columnIndex
    ) {
    }

    public static function create(
        int $index,
        int $columnIndex
    ): CypherPatternPosition {
        return new self(
            $index,
            $columnIndex
        );
    }

    public function equals(self $other): bool
    {
        return $this->index === $other->index;
    }

    public function gt(self $other): bool
    {
        return $this->index > $other->index;
    }

    public function gte(self $other): bool
    {
        return $this->gt($other) || $this->equals($other);
    }

    public function lt(self $other): bool
    {
        return $this->index < $other->index;
    }

    public function lte(self $other): bool
    {
        return $this->lt($other) || $this->equals($other);
    }

    public function jsonSerialize(): int
    {
        return $this->index;
    }
}
