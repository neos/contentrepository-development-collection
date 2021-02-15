<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionProcessor;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Dto\UriConstraints;

interface ContentDimensionProcessorInterface
{
    public function resolveDimensionSpacePoint(string &$requestPath, RouteParameters $routeParameters): DimensionSpacePoint;

    public function resolveDimensionUriConstraints(UriConstraints $uriConstraints, DimensionSpacePoint $dimensionSpacePoint): UriConstraints;
}
