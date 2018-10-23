<?php

namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Http;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddress;
use Neos\Flow\Mvc\Routing;

/**
 * The interface for content subgraph URI processors.
 */
interface ContentSubgraphUriProcessorInterface
{
    /**
     * @param NodeAddress $nodeAddress
     *
     * @return Routing\Dto\UriConstraints
     */
    public function resolveDimensionUriConstraints(NodeAddress $nodeAddress): Routing\Dto\UriConstraints;
}
