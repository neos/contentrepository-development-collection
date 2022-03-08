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
 * @implements \Iterator<CypherPatternFragment>
 */
#[Flow\Proxy(false)]
final class CypherPatternSourceIterator implements \Iterator
{
    /**
     * @var \Iterator<CypherPatternFragment>
     */
    private \Iterator $iterator;

    /**
     * @var array<int,CypherPatternFragment>
     */
    private array $lookAheadBuffer = [];

    private function __construct(
        private CypherPatternSource $source
    ) {
        $this->rewind();
    }

    public static function fromSource(CypherPatternSource $source): self
    {
        return new self($source);
    }

    public function lookAhead(int $length): ?CypherPatternFragment
    {
        $iterator = $this->iterator;
        $lookAhead = null;

        for ($i = 0; $i < $length; $i++) {
            if (isset($this->lookAheadBuffer[$i])) {
                $fragment = $this->lookAheadBuffer[$i];
            } elseif ($iterator->valid()) {
                $fragment = $iterator->current();
                $this->lookAheadBuffer[] = $fragment;
                $iterator->next();
            } else {
                return null;
            }

            if ($lookAhead === null) {
                $lookAhead = $fragment;
            } else {
                $lookAhead = $lookAhead->append($fragment);
            }
        }

        return $lookAhead;
    }

    public function willBe(string $characterSequence): ?CypherPatternFragment
    {
        if ($lookAhead = $this->lookAhead(mb_strlen($characterSequence))) {
            if ($lookAhead->value === $characterSequence) {
                return $lookAhead;
            }
        }

        return null;
    }

    public function skip(int $length): void
    {
        for ($i = 0; $i < $length; $i++) {
            $this->next();
        }
    }

    public function current(): CypherPatternFragment
    {
        if ($this->lookAheadBuffer) {
            return $this->lookAheadBuffer[0];
        } else {
            return $this->iterator->current();
        }
    }

    public function key(): int
    {
        return $this->iterator->key();
    }

    public function next(): void
    {
        if ($this->lookAheadBuffer) {
            array_shift($this->lookAheadBuffer);
        } else {
            $this->iterator->next();
        }
    }

    public function rewind(): void
    {
        $this->iterator = $this->source->getIterator();
    }

    public function valid(): bool
    {
        return !empty($this->lookAheadBuffer) || $this->iterator->valid();
    }
}
