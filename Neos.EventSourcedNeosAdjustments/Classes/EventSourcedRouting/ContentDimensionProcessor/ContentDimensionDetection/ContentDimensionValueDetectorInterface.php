<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionProcessor\ContentDimensionDetection;

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
use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimension;
use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimensionValue;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Dto\UriConstraints;

/**
 * Interface to detect the current request's dimension value
 */
interface ContentDimensionValueDetectorInterface
{
    public function detectValue(Dimension\ContentDimension $contentDimension, string &$requestPath, RouteParameters $routeParameters): ?Dimension\ContentDimensionValue;

    public function processUriConstraints(UriConstraints $uriConstraints, ContentDimension $contentDimension, ContentDimensionValue $contentDimensionValue): UriConstraints;
}
