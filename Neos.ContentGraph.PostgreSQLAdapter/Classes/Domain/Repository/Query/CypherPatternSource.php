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

/**
 * @implements \IteratorAggregate<CypherPatternFragment>
 */
#[Flow\Proxy(false)]
final class CypherPatternSource implements \IteratorAggregate
{
    private function __construct(
        public readonly string $contents
    ) {
    }

    public static function fromString(string $contents): self
    {
        return new self($contents);
    }

    public function equals(self $other): bool
    {
        return $this->contents === $other->contents;
    }

    /**
     * @return \Iterator<CypherPatternFragment>
     */
    public function getIterator(): \Iterator
    {
        $columnIndex = 0;
        $length = strlen($this->contents);

        for ($index = 0; $index < $length; $index++) {
            $character = $this->contents[$index];

            yield CypherPatternFragment::create(
                $character,
                CypherPatternPosition::create($index, $columnIndex),
                CypherPatternPosition::create($index, $columnIndex),
                $this
            );

            $columnIndex++;
        }
    }
}
