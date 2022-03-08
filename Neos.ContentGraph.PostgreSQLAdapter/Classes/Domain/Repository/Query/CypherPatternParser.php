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
final class CypherPatternParser
{
    /**
     * @throws CypherPatternParserFailed
     */
    public static function parseString(string $string): ?CypherPattern
    {
        $source = CypherPatternSource::fromString($string);
        $tokenizer = CypherPatternTokenizer::fromSource($source);
        $stream = CypherPatternTokenStream::fromTokenizer($tokenizer);

        return self::parseStream($stream);
    }

    /**
     * @throws CypherPatternParserFailed
     */
    public static function parseStream(CypherPatternTokenStream $stream): ?CypherPattern
    {
        if (!$stream->valid()) {
            return null;
        }

        return self::parsePattern($stream);
    }

    /**
     * @throws CypherPatternParserFailed
     */
    public static function parsePattern(
        CypherPatternTokenStream $stream
    ): CypherPattern {
        $path = [];
        while ($stream->valid()) {
            switch ($stream->current()->type) {
                case CypherPatternTokenType::NODE_START:
                    $path[] = self::parseNode($stream);
                    if (!$stream->current()->equals($stream->getLast())) {
                        $stream->next();
                    }
                    break;
                case CypherPatternTokenType::ESCAPE_CHARACTER:
                    $stream->next();
                    break;
                case CypherPatternTokenType::NODE_END:
                    if ($stream->current()->equals($stream->getLast())) {
                        break 2;
                    }
                    $stream->next();
                    break;
                default:
                    throw CypherPatternParserFailed::becauseOfUnexpectedToken($stream->current(), [
                        CypherPatternTokenType::NODE_START,
                        CypherPatternTokenType::NODE_END
                    ]);
            }
        }
        return CypherPattern::fromArray($path);
    }

    /**
     * @throws CypherPatternParserFailed
     */
    public static function parseNode(CypherPatternTokenStream $stream): CypherNode
    {
        $stream->consume(CypherPatternTokenType::NODE_START);
        $node = new CypherNode(
            VariableLiteral::fromTokenStream($stream)->value,
            LabelsLiteral::fromTokenStream($stream)->value,
            PropertiesLiteral::fromTokenStream($stream)->value
        );
        if (!$stream->current()->equals($stream->getLast())) {
            $stream->consume(CypherPatternTokenType::NODE_END);
        }

        return $node;
    }
}
