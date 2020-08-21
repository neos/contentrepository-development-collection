<?php
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\PropertyTypeIsInvalid;
use Neos\Flow\Annotations as Flow;

/**
 * The property type value object as declared in a NodeType
 *
 * Only for use on the write side to enforce constraints
 *
 * @Flow\Proxy(false)
 */
final class PropertyType
{
    const TYPE_BOOL = 'bool';
    const TYPE_INT = 'int';
    const TYPE_FLOAT = 'float';
    const TYPE_STRING = 'string';
    const TYPE_ARRAY = 'array';
    const TYPE_DATE = 'DateTimeImmutable';

    const PATTERN_ARRAY_OF = '/array<[^>]+>/';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $string): self
    {
        if ($string === 'reference' || $string === 'references') {
            throw PropertyTypeIsInvalid::becauseItIsReference();
        }
        if ($string === 'bool' || $string === 'boolean') {
            return self::bool();
        }
        if ($string === 'int' || $string === 'integer') {
            return self::int();
        }
        if ($string === 'DateTime' || $string === '\DateTime' || $string === 'DateTimeImmutable' || $string === '\DateTimeImmutable') {
            return self::date();
        }
        $className = $string[0] != '\\'
            ? '\\' . $string
            : $string;
        if ($string !== self::TYPE_FLOAT
            && $string !== self::TYPE_STRING
            && $string !== self::TYPE_ARRAY
            && !class_exists($className)
            && !interface_exists($className)
            && !preg_match(self::PATTERN_ARRAY_OF, $string))
        {
            throw PropertyTypeIsInvalid::becauseItIsUndefined($string);
        }

        return new self($string);
    }

    public static function bool(): self
    {
        return new self(self::TYPE_BOOL);
    }

    public static function int(): self
    {
        return new self(self::TYPE_INT);
    }

    public static function string(): self
    {
        return new self(self::TYPE_STRING);
    }

    public static function date(): self
    {
        return new self(self::TYPE_DATE);
    }

    public function isBool(): bool
    {
        return $this->value === self::TYPE_BOOL;
    }

    public function isInt(): bool
    {
        return $this->value === self::TYPE_INT;
    }

    public function isFloat(): bool
    {
        return $this->value === self::TYPE_FLOAT;
    }

    public function isString(): bool
    {
        return $this->value === self::TYPE_STRING;
    }

    public function isArray(): bool
    {
        return $this->value === self::TYPE_ARRAY;
    }

    public function isDate(): bool
    {
        return $this->value === self::TYPE_DATE;
    }

    public function matches($propertyValue): bool
    {
        if (is_null($propertyValue)) {
            return true;
        }
        if ($this->isBool()) {
            return is_bool($propertyValue);
        }
        if ($this->isInt()) {
            return is_int($propertyValue);
        }
        if ($this->isFloat()) {
            return is_float($propertyValue);
        }
        if ($this->isString()) {
            return is_string($propertyValue);
        }
        if ($this->isArray()) {
            return is_array($propertyValue);
        }

        $className = $this->value[0] != '\\'
            ? '\\' . $this->value
            : $this->value;

        return (class_exists($className) || interface_exists($className)) && $propertyValue instanceof $className;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
