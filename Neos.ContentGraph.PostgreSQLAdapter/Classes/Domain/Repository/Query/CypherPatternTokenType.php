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
enum CypherPatternTokenType:string
{
    // Nodes
    case NODE_START = '(';
    case NODE_END = ')';

    // Properties
    case PROPERTIES_START = '{';
    case PROPERTIES_END = '}';

    // Escape characters
    case ESCAPE_CHARACTER = '`';
    case STRING_ESCAPE_CHARACTER = '\'';

    // Literals
    case STRING_LITERAL_CONTENT = 'a-Z0-9';
    case COLON_LITERAL = ':';
    case UNDERSCORE_LITERAL = '_';
    case DASH_LITERAL = '-';
    case DOT_LITERAL = '.';
    case WHITESPACE_LITERAL = ' ';
}
