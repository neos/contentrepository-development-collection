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
final class PropertiesLiteral implements \Stringable
{
    private function __construct(
        public readonly CypherPatternToken $start,
        public readonly CypherPatternToken $end,
        public readonly CypherProperties $value
    ) {
    }

    public static function fromTokenStream(CypherPatternTokenStream $stream): self
    {
        $value = [];
        try {
            if ($stream->current()->type === CypherPatternTokenType::WHITESPACE_LITERAL) {
                $stream->next();
            }
            $stream->consume(CypherPatternTokenType::PROPERTIES_START);
            $start = $stream->current();
            $collectingValue = false;
            $currentPropertyName = null;
            while ($stream->valid()) {
                switch ($stream->current()->type) {
                    case CypherPatternTokenType::STRING_LITERAL_CONTENT:
                        if (!$collectingValue) {
                            $currentPropertyName = PropertyNameLiteral::fromTokenStream($stream)->value;
                        } else {
                            $currentPropertyValue = PropertyValueLiteral::fromTokenStream($stream)->value;
                            $value[$currentPropertyName] = $currentPropertyValue;
                            $collectingValue = false;
                        }
                        $stream->next();
                        break;
                    case CypherPatternTokenType::WHITESPACE_LITERAL:
                        $stream->next();
                        break;
                    case CypherPatternTokenType::STRING_ESCAPE_CHARACTER:
                        if (!is_null($currentPropertyName) && !$collectingValue) {
                            $currentPropertyValue = PropertyValueLiteral::fromTokenStream($stream)->value;
                            $value[$currentPropertyName] = $currentPropertyValue;
                            $collectingValue = false;
                        } else {
                            throw CypherPatternParserFailed::becauseOfUnexpectedToken(
                                $stream->current(),
                                [
                                    CypherPatternTokenType::STRING_LITERAL_CONTENT
                                ]
                            );
                        }
                        $stream->next();
                        break;
                    case CypherPatternTokenType::COLON_LITERAL:
                        if (!is_null($currentPropertyName) && !$collectingValue) {
                            $collectingValue = true;
                        } else {
                            throw CypherPatternParserFailed::becauseOfUnexpectedToken(
                                $stream->current(),
                                [
                                    CypherPatternTokenType::STRING_LITERAL_CONTENT
                                ]
                            );
                        }
                        $stream->next();
                        break;
                    case CypherPatternTokenType::PROPERTIES_END:
                        $stream->next();
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

        return new self($start, $end, CypherProperties::fromArray($value));
    }

    public function __toString(): string
    {
        return \json_encode($this->value, JSON_THROW_ON_ERROR);
    }
}
