<?php

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
use Neos\Flow\Exception;

/**
 * The exception to be thrown if a dimension space point is not yet occupied by a node in a node aggregate but is supposed to be.
 */
class DimensionSpacePointIsNotYetOccupied extends Exception
{
}
