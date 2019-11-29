<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * A collection of NodeAggregateIdentifiers
 *
 * @Flow\Proxy(false)
 */
final class NodeAggregateIdentifiers implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @var array|NodeAggregateIdentifier[]
     */
    private $values;

    /**
     * @var \ArrayIterator
     */
    private $iterator;

    private function __construct(array $values)
    {
        $this->values = $values;
        $this->iterator = new \ArrayIterator($values);
    }

    public static function fromArray(array $array): self
    {
        $values = [];
        foreach ($array as $item) {
            if ($item instanceof NodeAggregateIdentifier) {
                $values[(string) $item] = $item;
            } elseif (is_string($item)) {
                $values[$item] = NodeAggregateIdentifier::fromString($item);
            } else {
                throw new \InvalidArgumentException('Given value is neither a node aggregate identifier nor a valid serialization of one.', 1571341036);
            }
        }

        return new static($values);
    }

    public function getIterator(): \ArrayIterator
    {
        return $this->iterator;
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
