<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\PropertyType;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a given property does not match the type declared by the node type
 *
 * @Flow\Proxy(false)
 */
final class PropertyTypeDoesNotMatchNodeTypeSchema extends \DomainException
{
    public static function butWasSupposedTo(PropertyName $propertyName, string $attemptedPropertyType, NodeTypeName $nodeTypeName, PropertyType $declaredPropertyType): self
    {
        return new self('The value for property ' . $propertyName . ' is of type ' . $attemptedPropertyType . ', but node type ' . $nodeTypeName . ' declares ' . $declaredPropertyType, 1597938944);
    }
}
