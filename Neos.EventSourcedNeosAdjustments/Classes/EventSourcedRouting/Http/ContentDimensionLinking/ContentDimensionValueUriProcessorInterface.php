<?php

namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Http\ContentDimensionLinking;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\Dimension;
use Neos\Flow\Mvc\Routing;

/**
 * Interface for processing an URI to match the given content dimension value.
 */
interface ContentDimensionValueUriProcessorInterface
{
    /**
     * @param Routing\Dto\UriConstraints      $uriConstraints
     * @param Dimension\ContentDimension      $contentDimension
     * @param Dimension\ContentDimensionValue $contentDimensionValue
     * @param array|null                      $overrideOptions
     *
     * @return Routing\Dto\UriConstraints
     */
    public function processUriConstraints(
        Routing\Dto\UriConstraints $uriConstraints,
        Dimension\ContentDimension $contentDimension,
        Dimension\ContentDimensionValue $contentDimensionValue,
        array $overrideOptions = null
    ): Routing\Dto\UriConstraints;
}
