<?php declare(strict_types=1);

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

use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a given property scope is invalid
 *
 * @Flow\Proxy(false)
 */
final class PropertyScopeIsInvalid extends \DomainException
{
    public static function becauseItIsUndefined(string $attemptedValue): self
    {
        return new self('"' . $attemptedValue . '" is not one of the defined property scopes.', 1597950317);
    }
}
