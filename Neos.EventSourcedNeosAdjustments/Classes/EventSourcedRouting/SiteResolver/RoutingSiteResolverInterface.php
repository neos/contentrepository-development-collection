<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\SiteResolver;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Dto\UriConstraints;

/**
 * Common interface for routing site resolvers that can:
 * * Find the site node for the given route parameters (e.g. resolve the site for the current request URI host)
 * * Apply URI constraints for a given target site node name (e.g. turn a link into an absolute URL for cross-domain linking)
 */
interface RoutingSiteResolverInterface
{
    public function getCurrentSiteNode(RouteParameters $routeParameters): NodeName;

    public function buildUriConstraintsForSite(RouteParameters $routeParameters, NodeName $targetSiteNodeName): UriConstraints;
}
