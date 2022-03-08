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
 * @implements \IteratorAggregate<CypherPatternToken>
 */
#[Flow\Proxy(false)]
final class CypherPatternTokenizer implements \IteratorAggregate
{
    private CypherPatternSourceIterator $iterator;

    private function __construct(
        private CypherPatternSource $source
    ) {
        $this->iterator = CypherPatternSourceIterator::fromSource($this->source);
    }

    public static function fromSource(CypherPatternSource $source): self
    {
        return new self($source);
    }

    /**
     * @return \Iterator<int,CypherPatternToken>
     */
    public function getIterator(): \Iterator
    {
        $iterator = $this->iterator;
        while ($iterator->valid()) {
            $value = $iterator->current()->value;

            if ($value === '(') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::NODE_START,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === ')') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::NODE_END,
                    $iterator->current()
                );
                $iterator->next();
            } elseif (ctype_alnum($value)) {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::STRING_LITERAL_CONTENT,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === ':') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::COLON_LITERAL,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === '`') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::ESCAPE_CHARACTER,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === '.') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::DOT_LITERAL,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === '{') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::PROPERTIES_START,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === '\'') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::STRING_ESCAPE_CHARACTER,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === '-') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::DASH_LITERAL,
                    $iterator->current()
                );
                $iterator->next();
            } elseif (ctype_space($value)) {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::WHITESPACE_LITERAL,
                    $iterator->current()
                );
                $iterator->next();
            } elseif ($value === '}') {
                yield CypherPatternToken::fromFragment(
                    CypherPatternTokenType::PROPERTIES_END,
                    $iterator->current()
                );
                $iterator->next();
            } else {
                throw new \Exception(
                    'Invalid value at position ' . $iterator->key()
                        . ': expected valid cypher pattern character, got "' . $value . '"',
                    1646093846
                );
            }
        }
        return $this->iterator;
    }
}
