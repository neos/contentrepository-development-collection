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

use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\PropertyScopeIsInvalid;
use Neos\Flow\Annotations as Flow;

/**
 * The property scope value object as declared in a NodeType
 *
 * @Flow\Proxy(false)
 */
final class PropertyScope
{
    const SCOPE_NODE = 'node';
    const SCOPE_SPECIALIZATIONS = 'specializations';
    const SCOPE_NODE_AGGREGATE = 'nodeAggregate';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $string): self
    {
        if ($string !== self::SCOPE_NODE && $string !== self::SCOPE_SPECIALIZATIONS && $string !== self::SCOPE_NODE_AGGREGATE) {
            throw PropertyScopeIsInvalid::becauseItIsUndefined($string);
        }

        return new self($string);
    }

    public static function node(): self
    {
        return new self(self::SCOPE_NODE);
    }

    public static function specializations(): self
    {
        return new self(self::SCOPE_SPECIALIZATIONS);
    }

    public static function nodeAggregate(): self
    {
        return new self(self::SCOPE_NODE_AGGREGATE);
    }

    public function isNode(): bool
    {
        return $this->value === self::SCOPE_NODE;
    }

    public function isSpecializations(): bool
    {
        return $this->value === self::SCOPE_SPECIALIZATIONS;
    }

    public function isNodeAggregate(): bool
    {
        return $this->value === self::SCOPE_NODE_AGGREGATE;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
