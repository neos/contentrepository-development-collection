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
 * The currently expected pattern path segment type
 */
#[Flow\Proxy(false)]
enum ExpectedCypherPatternPathSegmentType:string
{
    case NODE = 'node';
    case RELATIONSHIP = 'relationship';

    public static function initial(): self
    {
        return self::NODE;
    }

    public function toggle(): self
    {
        return match($this) {
            self::NODE => self::RELATIONSHIP,
            self::RELATIONSHIP => self::NODE
        };
    }

    public function matches(CypherNode $pathSegment): bool
    {
        return $pathSegment instanceof CypherNode && $this === self::NODE;
    }
}
