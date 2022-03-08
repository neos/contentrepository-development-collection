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
 * A collection of cypher node labels, represented by ":`Acme.Site:Document`:`Acme.Site:SpecialMixin`"
 * in a cypher pattern
 *
 * @implements \IteratorAggregate<int,CypherNodeLabel>
 */
#[Flow\Proxy(false)]
final class CypherNodeLabels implements \IteratorAggregate, \Stringable
{
    /**
     * @var \ArrayIterator<int,CypherNodeLabel>
     */
    private \ArrayIterator $iterator;

    private function __construct(
        /**
         * @var array<int,CypherNodeLabel>
         */
        public readonly array $labels
    ) {
        $this->iterator = new \ArrayIterator($labels);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<int,CypherNodeLabel> $array
     */
    public static function fromArray(array $array): self
    {
        foreach ($array as $label) {
            if (!$label instanceof CypherNodeLabel) {
                throw new \InvalidArgumentException(
                    'CypherNodeLabels may only consist of ' . CypherNodeLabel::class . ' objects',
                    1645884643
                );
            }
        }
        return new self($array);
    }

    /**
     * @return \ArrayIterator<int,CypherNodeLabel>
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->iterator;
    }

    public function __toString(): string
    {
        return implode('', $this->labels);
    }
}
