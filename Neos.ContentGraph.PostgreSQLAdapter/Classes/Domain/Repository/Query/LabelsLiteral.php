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
final class LabelsLiteral implements \Stringable
{
    private function __construct(
        public readonly CypherPatternToken $start,
        public readonly CypherPatternToken $end,
        public readonly CypherNodeLabels $value
    ) {
    }

    public static function fromTokenStream(CypherPatternTokenStream $stream): self
    {
        $value = [];
        try {
            $stream->consume(CypherPatternTokenType::COLON_LITERAL);
            $start = $stream->current();
            $currentLabel = '';
            $escaped = false;
            while ($stream->valid()) {
                switch ($stream->current()->type) {
                    case CypherPatternTokenType::ESCAPE_CHARACTER:
                        $escaped = !$escaped;
                        $stream->next();
                        break;
                    case CypherPatternTokenType::STRING_LITERAL_CONTENT:
                    case CypherPatternTokenType::UNDERSCORE_LITERAL:
                    case CypherPatternTokenType::DOT_LITERAL:
                        $currentLabel .= $stream->current()->value;
                        $stream->next();
                        break;
                    case CypherPatternTokenType::COLON_LITERAL:
                        if ($escaped) {
                            $currentLabel .= $stream->current()->value;
                        } else {
                            $value[] = CypherNodeLabel::fromString($currentLabel);
                            $currentLabel = '';
                        }
                        $stream->next();
                        break;
                    case CypherPatternTokenType::WHITESPACE_LITERAL: // node without further labels and with properties
                    case CypherPatternTokenType::NODE_END: // node without further labels and without properties
                        $value[] = CypherNodeLabel::fromString($currentLabel);
                        break 2;
                    default:
                        throw CypherPatternParserFailed::becauseOfUnexpectedToken(
                            $stream->current(),
                            [
                                CypherPatternTokenType::STRING_LITERAL_CONTENT
                            ]
                        );
                }
            }
        } catch (CypherPatternParserFailed $e) {
            // we have no labels
            $start = $stream->current();
        }

        $end = $stream->current();

        return new self($start, $end, CypherNodeLabels::fromArray($value));
    }

    public function __toString(): string
    {
        return \json_encode($this->value, JSON_THROW_ON_ERROR);
    }
}
