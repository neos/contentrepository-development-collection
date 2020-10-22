<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\ValueObject;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\PropertyCollection;
use Neos\Flow\Annotations as Flow;

/**
 * "Raw" property values as saved in the event log // in projections.
 *
 * This means: each "value" must be a simple PHP data type.
 *
 * NOTE: if a value is set to NULL in SerializedPropertyValues, this means the key should be unset,
 * because we treat NULL and "not set" the same from an API perspective.
 *
 * @Flow\Proxy(false)
 */
final class SerializedPropertyValues implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var array|SerializedPropertyValue[]
     */
    private $values = [];

    /**
     * @var \ArrayIterator
     */
    protected $iterator;

    private function __construct(array $values)
    {
        $this->values = $values;
        $this->iterator = new \ArrayIterator($this->values);
    }

    public function merge(SerializedPropertyValues $other): SerializedPropertyValues
    {
        // here, we skip null values
        return new SerializedPropertyValues(array_filter(array_merge($this->values, $other->getValues()), fn ($value) => $value !== null));
    }

    public function propertyExists($propertyName): bool
    {
        return isset($this->values[$propertyName]);
    }

    public function getProperty($propertyName): ?SerializedPropertyValue
    {
        if (!isset($this->values[$propertyName])) {
            return null;
        }

        return $this->values[$propertyName];
    }

    /**
     * @param SerializedPropertyValue[] values
     *@return array|SerializedPropertyValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public static function fromArray(array $propertyValues): self
    {
        $values = [];
        foreach ($propertyValues as $propertyName => $propertyValue) {
            if ($propertyValue === null) {
                // this case means we want to un-set a property.
                $values[$propertyName] = null;
            } elseif (is_array($propertyValue)) {
                $values[$propertyName] = SerializedPropertyValue::fromArray($propertyValue);
            } elseif ($propertyValue instanceof SerializedPropertyValue) {
                $values[$propertyName] = $propertyValue;
            } else {
                throw new \InvalidArgumentException(sprintf('Invalid property value. Expected instance of %s, got: %s', SerializedPropertyValue::class, is_object($propertyValue) ? get_class($propertyValue) : gettype($propertyValue)), 1546524480);
            }
        }

        return new static($values);
    }

    public static function fromNode(NodeInterface $node): self
    {
        $values = [];

        $nodeProperties = $node->getProperties();

        if (!($nodeProperties instanceof PropertyCollection)) {
            throw new \RuntimeException('TODO: Node properties are no Property Collection');
        }
        return $nodeProperties->getSerializedPropertyValues();
    }

    /**
     * @return SerializedPropertyValue[]|\ArrayIterator<SerializedPropertyValue>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->values);
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function getPlainValues(): array
    {
        $values = [];
        foreach ($this->values as $propertyName => $propertyValue) {
            $values[$propertyName] = $propertyValue->getValue();
        }

        return $values;
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
