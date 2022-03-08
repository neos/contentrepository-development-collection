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
final class CypherPatternToken implements \Stringable
{
    private function __construct(
        public readonly CypherPatternTokenType $type,
        public readonly string $value,
        public readonly CypherPatternPosition $start,
        public readonly CypherPatternPosition $end,
        public readonly CypherPatternSource $source
    ) {
    }

    public static function fromFragment(
        CypherPatternTokenType $type,
        CypherPatternFragment $fragment
    ): self {
        return new self(
            $type,
            $fragment->value,
            $fragment->start,
            $fragment->end,
            $fragment->source
        );
    }

    public function equals(self $other): bool
    {
        return (
            $this->type === $other->type
            && $this->value === $other->value
            && $this->start->equals($other->start)
            && $this->end->equals($other->end)
            && $this->source->equals($other->source)
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
