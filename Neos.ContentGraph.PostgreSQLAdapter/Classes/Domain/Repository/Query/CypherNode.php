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
 * A cypher node, representing a node from a pattern,
 * like (n:`Acme.Site:Document`:`Acme.Site:SpecialMixin` {title: 'Important'})
 */
#[Flow\Proxy(false)]
final class CypherNode implements \Stringable
{
    public function __construct(
        public readonly ?string $variable,
        public readonly CypherNodeLabels $labels,
        public readonly CypherProperties $properties
    ) {
    }

    public function __toString(): string
    {
        return '(' . $this->variable . $this->labels . $this->properties . ')';
    }
}
