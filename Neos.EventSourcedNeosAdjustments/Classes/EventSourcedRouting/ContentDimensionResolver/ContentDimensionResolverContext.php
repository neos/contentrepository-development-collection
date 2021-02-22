<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionResolver;

use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimensionIdentifier;
use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimensionValue;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class ContentDimensionResolverContext
{
    private string $remainingUriPath;
    private RouteParameters $routeParameters;
    private array $dimensionSpacePointCoordinates;

    private function __construct(string $remainingUriPath, RouteParameters $routeParameters, array $dimensionSpacePointCoordinates)
    {
        $this->remainingUriPath = $remainingUriPath;
        $this->routeParameters = $routeParameters;
        $this->dimensionSpacePointCoordinates = $dimensionSpacePointCoordinates;
    }

    public static function fromUriPathAndRouteParameters(string $uriPath, RouteParameters $routeParameters): self
    {
        return new self($uriPath, $routeParameters, []);
    }

    public function addDimensionSpacePointCoordinate(ContentDimensionIdentifier $dimensionIdentifier, ContentDimensionValue $dimensionValue): self
    {
        $dimensionSpacePointCoordinates = $this->dimensionSpacePointCoordinates;
        $dimensionSpacePointCoordinates[(string)$dimensionIdentifier] = $dimensionValue->getValue();
        return new self($this->remainingUriPath, $this->routeParameters, $dimensionSpacePointCoordinates);
    }

    public function withRemainingUriPath(string $remainingUriPath): self
    {
        return new self($remainingUriPath, $this->routeParameters, $this->dimensionSpacePointCoordinates);
    }

    public function remainingUriPath(): string
    {
        return $this->remainingUriPath;
    }

    public function dimensionSpacePoint(): DimensionSpacePoint
    {
        return DimensionSpacePoint::fromArray($this->dimensionSpacePointCoordinates);
    }
}
