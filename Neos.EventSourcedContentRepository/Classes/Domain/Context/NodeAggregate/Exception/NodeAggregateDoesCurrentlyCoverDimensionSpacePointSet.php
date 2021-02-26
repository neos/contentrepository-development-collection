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

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a node aggregate does currently cover a given set of dimension space points but is not supposed to
 *
 * @Flow\Proxy(false)
 */
final class NodeAggregateDoesCurrentlyCoverDimensionSpacePointSet extends \DomainException
{
    public static function butIsNotSupposedTo(NodeAggregateIdentifier $nodeAggregateIdentifier, DimensionSpacePointSet $coveredDimensionSpacePoints): self
    {
        return new self('Node aggregate "' . $nodeAggregateIdentifier . '" does currently cover dimension space point set ' . json_encode($coveredDimensionSpacePoints) . '.', 1614296176);
    }
}
