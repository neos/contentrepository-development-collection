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
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;

/**
 * The exception to be thrown if a given property name is undeclared in a node type
 */
final class PropertyNameIsUndeclaredInNodeType extends \DomainException
{
    public static function butWasSupposedToBe(PropertyName $attemptedPropertyName, NodeTypeName $nodeTypeName): PropertyNameIsUndeclaredInNodeType
    {
        return new static('Given property name "' . $attemptedPropertyName . '" is not defined in node type "' . $nodeTypeName . '"', 1571308984);
    }
}
