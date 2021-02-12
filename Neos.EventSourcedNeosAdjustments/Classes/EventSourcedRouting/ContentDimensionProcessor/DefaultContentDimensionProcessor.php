<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionProcessor;

use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimension;
use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimensionSourceInterface;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionProcessor\ContentDimensionDetection\ContentDimensionValueDetectorInterface;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionProcessor\ContentDimensionDetection\UriPathSegmentContentDimensionValueDetector;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Dto\UriConstraints;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

class DefaultContentDimensionProcessor implements ContentDimensionProcessorInterface
{

    private ContentDimensionSourceInterface $dimensionSource;
    private ObjectManagerInterface $objectManager;

    public function __construct(ContentDimensionSourceInterface $dimensionSource, ObjectManagerInterface $objectManager)
    {
        $this->dimensionSource = $dimensionSource;
        $this->objectManager = $objectManager;
    }

    public function resolveDimensionSpacePoint(string &$requestPath, RouteParameters $routeParameters): DimensionSpacePoint
    {
        $coordinates = [];

        $dimensions = $this->dimensionSource->getContentDimensionsOrderedByPriority();
        #$this->sortDimensionsByOffset($dimensions);
        foreach ($dimensions as $rawDimensionIdentifier => $contentDimension) {
            $detector = $this->resolveContentDimensionValueDetector($contentDimension);
            $dimensionValue = $detector->detectValue($contentDimension, $requestPath, $routeParameters);
            if ($dimensionValue !== null) {
                $coordinates[$rawDimensionIdentifier] = (string)$dimensionValue;
            }
        }

        return new DimensionSpacePoint($coordinates);
    }

    public function resolveDimensionUriConstraints(UriConstraints $uriConstraints, DimensionSpacePoint $dimensionSpacePoint): UriConstraints
    {
        $dimensions = $this->dimensionSource->getContentDimensionsOrderedByPriority();
        foreach ($dimensions as $rawContentDimensionIdentifier => $contentDimension) {
            $contentDimensionValue = $contentDimension->getValue($dimensionSpacePoint->getCoordinates()[$rawContentDimensionIdentifier]);
            if ($contentDimensionValue === null) {
                // TODO what to do here?
                continue;
            }
            $linkProcessor = $this->resolveContentDimensionValueDetector($contentDimension);
            $linkProcessor->processUriConstraints($uriConstraints, $contentDimension, $contentDimensionValue);
        }
        return $uriConstraints;
    }

    private function resolveContentDimensionValueDetector(ContentDimension $contentDimension): ContentDimensionValueDetectorInterface
    {
        $detectorClassName = $contentDimension->getConfigurationValue('resolution.className');
        // HACK
        if (empty($detectorClassName)) {
            $detectorClassName = UriPathSegmentContentDimensionValueDetector::class;
        }
        $detector = $this->objectManager->get($detectorClassName);
        if (!$detector instanceof ContentDimensionValueDetectorInterface) {
            throw new \InvalidArgumentException(sprintf('"%s", configured as content dimension value detector for content dimension "%s", does not implement %s. Please check your dimension configuration.', $detectorClassName, $contentDimension->getIdentifier(), ContentDimensionValueDetectorInterface::class), 1510826082);
        }
        return $detector;
    }
}
