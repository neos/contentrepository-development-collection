<?php
namespace Neos\ContentRepository\Intermediary\Domain\Property;

/*
 * This file is part of the Neos.ContentRepository.Intermediary package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Intermediary\Domain\Exception\PropertyTypeIsInvalid;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\UriInterface;

/**
 * The property type value object as declared in a NodeType
 *
 * Only for use on the write side to enforce constraints
 *
 * @Flow\Proxy(false)
 */
final class PropertyType
{
    const TYPE_BOOL = 'boolean';
    const TYPE_INT = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_STRING = 'string';
    const TYPE_ARRAY = 'array';
    const TYPE_DATE = 'DateTimeImmutable';

    const PATTERN_ARRAY_OF = '/array<[^>]+>/';

    private string $value;

    private bool $isNullable;

    private function __construct(string $value, bool $isNullable)
    {
        $this->value = $value;
        $this->isNullable = $isNullable;
    }

    public static function fromNodeTypeDeclaration(string $declaration): self
    {
        if (\mb_strpos($declaration, '?') === 0) {
            $declaration = \mb_substr($declaration, 1);
            $isNullable = true;
        }
        // we always assume nullability for now
        $isNullable = true;
        if ($declaration === 'reference' || $declaration === 'references') {
            throw PropertyTypeIsInvalid::becauseItIsReference();
        }
        if ($declaration === 'bool' || $declaration === 'boolean') {
            return self::bool($isNullable);
        }
        if ($declaration === 'int' || $declaration === 'integer') {
            return self::int($isNullable);
        }
        if ($declaration === 'float' || $declaration === 'double') {
            return self::float($isNullable);
        }
        if (in_array($declaration, ['DateTime', '\DateTime', 'DateTimeImmutable', '\DateTimeImmutable', 'DateTimeInterface', '\DateTimeInterface'])) {
            return self::date($isNullable);
        }
        if ($declaration === 'Uri' || $declaration === Uri::class || $declaration === UriInterface::class) {
            $declaration = Uri::class;
        }
        $className = $declaration[0] != '\\'
            ? '\\' . $declaration
            : $declaration;
        if ($declaration !== self::TYPE_FLOAT
            && $declaration !== self::TYPE_STRING
            && $declaration !== self::TYPE_ARRAY
            && !class_exists($className)
            && !interface_exists($className)
            && !preg_match(self::PATTERN_ARRAY_OF, $declaration)) {
            throw PropertyTypeIsInvalid::becauseItIsUndefined($declaration);
        }

        return new self($declaration, $isNullable);
    }

    public static function bool(bool $isNullable): self
    {
        return new self(self::TYPE_BOOL, $isNullable);
    }

    public static function int(bool $isNullable): self
    {
        return new self(self::TYPE_INT, $isNullable);
    }

    public static function string(bool $isNullable): self
    {
        return new self(self::TYPE_STRING, $isNullable);
    }

    public static function float(bool $isNullable): self
    {
        return new self(self::TYPE_FLOAT, $isNullable);
    }

    public static function date(bool $isNullable): self
    {
        return new self(self::TYPE_DATE, $isNullable);
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

    public function isArrayOf(): bool
    {
        return preg_match(self::PATTERN_ARRAY_OF, $this->value);
    }

    public function isDate(): bool
    {
        return $this->value === self::TYPE_DATE;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function getArrayOfClassName(): string
    {
        return \mb_substr($this->value, 6, \mb_strlen($this->value) - 7);
    }

    public function isMatchedBy($propertyValue): bool
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
        if ($this->isArrayOf()) {
            if (is_array($propertyValue)) {
                $className = $this->getArrayOfClassName();
                foreach ($propertyValue as $object) {
                    if (!$object instanceof $className) {
                        return false;
                    }
                }
                return true;
            }
        }

        if ($this->isDate()) {
            return $propertyValue instanceof \DateTimeInterface;
        }

        $className = $this->value[0] != '\\'
            ? '\\' . $this->value
            : $this->value;

        return (class_exists($className) || interface_exists($className)) && $propertyValue instanceof $className;
    }

    public function getSerializationType(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
