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
final class PropertyValueLiteral
{
    private function __construct(
        public readonly CypherPatternToken $start,
        public readonly CypherPatternToken $end,
        public readonly ?string $value
    ) {
    }

    public static function fromTokenStream(CypherPatternTokenStream $stream): self
    {
        $start = $stream->current();

        $value = '';
        $stringStarted = false;
        while ($stream->valid()) {
            switch ($stream->current()->type) {
                case CypherPatternTokenType::STRING_ESCAPE_CHARACTER:
                    if (!$stringStarted) {
                        $stringStarted = true;
                        $stream->next();
                        break;
                    } else {
                        $stream->next();
                        break 2;
                    }
                case CypherPatternTokenType::STRING_LITERAL_CONTENT:
                case CypherPatternTokenType::DASH_LITERAL:
                case CypherPatternTokenType::UNDERSCORE_LITERAL:
                    $value .= $stream->current()->value;
                    $stream->next();
                    break;
                case CypherPatternTokenType::COLON_LITERAL:
                    break 2;
                default:
                    throw CypherPatternParserFailed::becauseOfUnexpectedToken(
                        $stream->current(),
                        [
                            CypherPatternTokenType::STRING_LITERAL_CONTENT,
                            CypherPatternTokenType::COLON_LITERAL
                        ]
                    );
            }
        }

        $end = $stream->current();

        return new self($start, $end, $value !== '' ? $value : null);
    }

    public function __toString(): string
    {
        return $this->value ?: '';
    }
}
