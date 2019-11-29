<?php
declare(strict_types=1);

namespace Neos\ContentRepository\History\Domain\History;

/*
 * This file is part of the Neos.ContentRepository.History package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;

/**
 * The collection for history entry type value object
 */
final class HistoryEntryTypes implements \JsonSerializable, \IteratorAggregate
{
    /**
     * @var array|HistoryEntryType[]
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
        foreach ($array as $value) {
            if ($value instanceof HistoryEntryType) {
                $values[] = $value;
            } elseif (is_string($value)) {
                $values[] = HistoryEntryType::fromString($value);
            } else {
                throw new \InvalidArgumentException('Given value is neither a history entry type nor a valid serialization of one.', 1571386008);
            }
        }

        return new static($values);
    }

    public function getIterator(): \ArrayIterator
    {
        return $this->iterator;
    }

    public function toPlainArray(): array
    {
        $plainArray = [];

        foreach ($this->values as $value) {
            $plainArray[] = (string) $value;
        }

        return $plainArray;
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
