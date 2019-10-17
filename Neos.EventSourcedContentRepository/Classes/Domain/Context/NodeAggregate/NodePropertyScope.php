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

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodePropertyScopeIsInvalid;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\Flow\Annotations as Flow;

/**
 * The scope of a node property
 *
 * @Flow\Proxy(false)
 */
final class NodePropertyScope implements \JsonSerializable
{
    /**
     * Such a property can be set on a per-node basis
     */
    const SCOPE_NODE = 'node';

    /**
     * Such a property's value is shared among all variants in a node aggregate
     */
    const SCOPE_NODE_AGGREGATE = 'nodeAggregate';

    /**
     * @var string
     */
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if ($value !== self::SCOPE_NODE && $value !== self::SCOPE_NODE_AGGREGATE) {
            throw NodePropertyScopeIsInvalid::becauseItIsNotOneOfThePredefinedConstants($value);
        }

        return new static($value);
    }

    public static function node(): self
    {
        return new static(self::SCOPE_NODE);
    }

    public static function nodeAggregate(): self
    {
        return new static(self::SCOPE_NODE_AGGREGATE);
    }

    /**
     * @param NodeType $nodeType
     * @param PropertyName $propertyName
     * @return NodePropertyScope
     * @todo teach this to the node type itself
     */
    public static function fromNodeTypeAndPropertyName(NodeType $nodeType, PropertyName $propertyName): self
    {
        if (isset($nodeType->getProperties()[(string) $propertyName]['scope'])) {
            return self::fromString($nodeType->getProperties()[(string) $propertyName]['scope']);
        }

        return self::node();
    }

    public function isNode(): bool
    {
        return $this->value === self::SCOPE_NODE;
    }

    public function isNodeAggregate(): bool
    {
        return $this->value === self::SCOPE_NODE_AGGREGATE;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
