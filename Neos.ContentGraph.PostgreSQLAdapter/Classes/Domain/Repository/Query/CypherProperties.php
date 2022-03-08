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
 * A collection of cypher properties, represented by "{myProperty: 'myValue', myOtherProperty:42}" in a cypher pattern
 *
 * @implements \IteratorAggregate<string,mixed>
 */
#[Flow\Proxy(false)]
final class CypherProperties implements \IteratorAggregate, \Stringable
{
    /**
     * @var \ArrayIterator<string,mixed>
     */
    private \ArrayIterator $iterator;

    private function __construct(
        /**
         * @var array<string,mixed>
         */
        public readonly array $properties
    ) {
        $this->iterator = new \ArrayIterator($properties);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string,mixed> $array
     */
    public static function fromArray(array $array): self
    {
        return new self($array);
    }

    /**
     * @return \ArrayIterator<string,mixed>
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->iterator;
    }

    public function __toString(): string
    {
        if (empty($this->properties)) {
            return '';
        }
        $result = '{';
        $i = 0;
        foreach ($this->properties as $propertyName => $property) {
            if ($i === 1) {
                $result .= ',';
            }
            $result .= $propertyName . ': \'' . $property . '\'';
            $i++;
        }
        $result .= '}';

        return $result;
    }
}
