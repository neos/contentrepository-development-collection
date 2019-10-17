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

/**
 * The exception to be thrown if a given property scope is invalid
 */
final class NodePropertyScopeIsInvalid extends \DomainException
{
    public static function becauseItIsNotOneOfThePredefinedConstants(string $attemptedValue): NodePropertyScopeIsInvalid
    {
        return new static('Given value "' . $attemptedValue . '" is not a valid property scope, must be one of the predefined constants.', 1571313013);
    }
}
