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
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\Flow\Annotations as Flow;

/**
 * The scope of a node property
 *
 * @Flow\Proxy(false)
 */
final class ScopedPropertyValues implements \JsonSerializable
{
    /**
     * @var array|PropertyValues[]
     */
    private $propertyValues;

    private function __construct(array $propertyValues)
    {
        $this->propertyValues = $propertyValues;
    }

    public static function separateFromPropertyValues(NodeType $nodeType, PropertyValues $propertyValues): self
    {
        $scopedPropertyValues = [];
        foreach ($propertyValues as $rawPropertyName => $propertyValue) {
            $scope = NodePropertyScope::fromNodeTypeAndPropertyName($nodeType, PropertyName::fromString($rawPropertyName));
            $scopedPropertyValues[(string) $scope][$rawPropertyName] = $propertyValue;
        }

        foreach ($scopedPropertyValues as &$propertyValuesForScope) {
            $propertyValuesForScope = PropertyValues::fromArray($propertyValuesForScope);
        }
        return new static($scopedPropertyValues);
    }

    public function get(NodePropertyScope $scope): ?PropertyValues
    {
        return $this->propertyValues[(string) $scope] ?? null;
    }

    public function jsonSerialize(): array
    {
        return $this->propertyValues;
    }
}
