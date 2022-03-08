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
final class CypherPatternParserFailed extends \Exception
{
    private function __construct(
        public readonly ?CypherPatternToken $token,
        string $message
    ) {
        parent::__construct('Parser failed: ' . $message);
    }

    /**
     * @param array<int,CypherPatternTokenType> $expectedTypes
     */
    public static function becauseOfUnexpectedToken(
        CypherPatternToken $token,
        array $expectedTypes = []
    ): self {
        $message = sprintf(
            'Encountered unexpected token "%s" of type "%s".',
            $token->value,
            $token->type->value
        );

        if ($count = count($expectedTypes)) {
            if ($count > 1) {
                $last = array_pop($expectedTypes);

                $message .= sprintf(
                    ' Expected one of %s or %s.',
                    join(', ', array_map(
                        fn (CypherPatternTokenType $type): string => $type->value,
                        $expectedTypes
                    )),
                    $last->value
                );
            } else {
                $message .= sprintf(
                    ' Expected %s.',
                    $expectedTypes[0]->value
                );
            }
        }

        return new self($token, $message);
    }

    public static function becauseOfUnexpectedEndOfPattern(CypherPatternTokenStream $stream): self
    {
        return new self($stream->getLast(), 'Encountered unexpected end of pattern.');
    }
}
