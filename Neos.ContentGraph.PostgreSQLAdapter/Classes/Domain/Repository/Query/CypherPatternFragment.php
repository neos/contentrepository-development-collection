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
final class CypherPatternFragment implements \Stringable
{
    private function __construct(
        public readonly string $value,
        public readonly CypherPatternPosition $start,
        public readonly CypherPatternPosition $end,
        public readonly CypherPatternSource $source
    ) {
    }

    public static function create(
        string $value,
        CypherPatternPosition $start,
        CypherPatternPosition $end,
        CypherPatternSource $source
    ): self {
        return new self($value, $start, $end, $source);
    }

    public function append(self $other): self
    {
        return new self(
            $this->value . $other->value,
            $this->start,
            $other->end,
            $this->source
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
