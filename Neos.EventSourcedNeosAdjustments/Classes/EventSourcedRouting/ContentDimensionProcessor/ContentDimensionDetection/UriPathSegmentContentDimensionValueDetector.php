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
 * URI path segment based dimension value detector
 */
final class UriPathSegmentContentDimensionValueDetector implements ContentDimensionValueDetectorInterface
{
    public function detectValue(ContentDimension $contentDimension, string &$requestPath, RouteParameters $routeParameters): ?ContentDimensionValue
    {
        /** @var string[] $requestPathSegments */
        $requestPathSegments = explode('/', $requestPath);
        $firstRequestPathSegment = $requestPathSegments[0] ?? '';
        foreach ($contentDimension->getValues() as $contentDimensionValue) {
            $resolutionValue = $contentDimensionValue->getConfigurationValue('resolution.value');
            if ($resolutionValue === $firstRequestPathSegment) {
                $requestPath = ltrim(substr($requestPath, strlen($resolutionValue)), '\/');
                return $contentDimensionValue;
            }
        }
        return $contentDimension->getDefaultValue();
    }

    public function processUriConstraints(UriConstraints $uriConstraints, ContentDimension $contentDimension, ContentDimensionValue $contentDimensionValue): UriConstraints
    {
        $pathSegmentPart = $contentDimensionValue->getConfigurationValue('resolution.value');

        return $uriConstraints->withPathPrefix($pathSegmentPart, true);
    }
}
