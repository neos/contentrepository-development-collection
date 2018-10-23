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
 * Host suffix based content dimension value uri processor.
 */
final class HostSuffixContentDimensionValueUriProcessor implements ContentDimensionValueUriProcessorInterface
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
    ): Routing\Dto\UriConstraints {
        $hostSuffixesToBeReplaced = [];
        foreach ($contentDimension->getValues() as $availableDimensionValue) {
            $resolutionValue = $availableDimensionValue->getConfigurationValue('resolution.value');
            if ($resolutionValue) {
                $hostSuffixesToBeReplaced[] = $resolutionValue;
            }
        }

        return $uriConstraints->withHostSuffix($contentDimensionValue->getConfigurationValue('resolution.value') ?: '', $hostSuffixesToBeReplaced);
    }
}
