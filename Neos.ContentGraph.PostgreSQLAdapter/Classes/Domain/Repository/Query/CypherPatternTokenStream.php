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
 * @implements \Iterator<CypherPatternToken>
 */
#[Flow\Proxy(false)]
final class CypherPatternTokenStream implements \Iterator
{
    /**
     * @var \Iterator<CypherPatternToken>
     */
    private \Iterator $iterator;

    /**
     * @var array<int,CypherPatternToken>
     */
    private array $lookAheadBuffer = [];

    private CypherPatternToken $last;

    private function __construct(
        private CypherPatternTokenizer $tokenizer
    ) {
        $this->rewind();
    }

    public static function fromTokenizer(CypherPatternTokenizer $tokenizer): self
    {
        return new self($tokenizer);
    }

    public function getLast(): CypherPatternToken
    {
        return $this->last;
    }

    public function lookAhead(int $length): ?CypherPatternToken
    {
        $count = count($this->lookAheadBuffer);

        if ($count > $length) {
            return $this->lookAheadBuffer[$length - 1];
        }

        $iterator = $this->iterator;
        $token = null;

        for ($i = 0; $i < $length - $count; $i++) {
            if (!$iterator->valid()) {
                return null;
            }

            $token = $iterator->current();
            $this->lookAheadBuffer[] = $token;
            $iterator->next();
        }

        return $token;
    }

    public function skip(int $length): void
    {
        for ($i = 0; $i < $length; $i++) {
            $this->next();
        }
    }

    /**
     * @throws CypherPatternParserFailed
     */
    public function consume(CypherPatternTokenType $type): CypherPatternToken
    {
        if ($this->current()->type === $type) {
            $result = $this->current();
            $this->next();
            return $result;
        } else {
            throw CypherPatternParserFailed::becauseOfUnexpectedToken(
                $this->current(),
                [$type]
            );
        }
    }

    /**
     * @throws CypherPatternParserFailed
     */
    public function current(): CypherPatternToken
    {
        if (!$this->valid()) {
            throw CypherPatternParserFailed::becauseOfUnexpectedEndOfPattern($this);
        }

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

        if ($this->valid()) {
            $this->last = $this->iterator->current();
        }
    }

    public function rewind(): void
    {
        $this->iterator = $this->tokenizer->getIterator();
        $this->last = $this->iterator->current();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }
}
