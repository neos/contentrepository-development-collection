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

use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a node aggregate does currently cover a given dimension space point but is not supposed to
 *
 * @Flow\Proxy(false)
 */
final class NodeAggregateDoesCurrentlyCoverDimensionSpacePoint extends \DomainException
{
}
