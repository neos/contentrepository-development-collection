<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionResolver;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\Flow\Mvc\Routing\Dto\UriConstraints;

/**
 * Common interface for content dimension resolvers that can:
 * * Determine the DimensionSpacePoint for an incoming request (using the ContentDimensionResolverContext DTO in order to make this chainable)
 * * Apply URI constraints according to the given DimensionSpacePoint (e.g. add a path prefix for the resolved content dimensions)
 */
interface ContentDimensionResolverInterface
{
    /**
     * @param ContentDimensionResolverContext $context
     * @return ContentDimensionResolverContext Note: This can contain an "incomplete" dimension space point... TODO
     */
    public function resolveDimensionSpacePoint(ContentDimensionResolverContext $context): ContentDimensionResolverContext;

    public function resolveDimensionUriConstraints(UriConstraints $uriConstraints, DimensionSpacePoint $dimensionSpacePoint): UriConstraints;
}
