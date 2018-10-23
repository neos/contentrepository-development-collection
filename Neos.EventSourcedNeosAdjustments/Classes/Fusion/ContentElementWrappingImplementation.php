<?php

namespace Neos\EventSourcedNeosAdjustments\Fusion;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\EventSourcedNeosAdjustments\ContentElementWrapping\ContentElementWrappingService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

/**
 * Adds meta data attributes to the processed Content Element.
 */
class ContentElementWrappingImplementation extends AbstractFusionObject
{
    /**
     * @Flow\Inject
     *
     * @var PrivilegeManagerInterface
     */
    protected $privilegeManager;

    /**
     * @Flow\Inject
     *
     * @var ContentElementWrappingService
     */
    protected $contentElementWrappingService;

    /**
     * The string to be processed.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->fusionValue('value');
    }

    /**
     * Additional attributes to be rendered in the ContentElementWrapping.
     *
     * @return array
     */
    public function getAdditionalAttributes()
    {
        return $this->fusionValue('additionalAttributes') ?? [];
    }

    /**
     * Evaluate this Fusion object and return the result.
     *
     * @return mixed
     */
    public function evaluate()
    {
        $content = $this->getValue();

        /** @var $node TraversableNodeInterface */
        $node = $this->fusionValue('node');
        if (!$node instanceof TraversableNodeInterface) {
            return $content;
        }

        if (!$this->privilegeManager->isPrivilegeTargetGranted('Neos.Neos:Backend.GeneralAccess')) {
            return $content;
        }

        if ($this->fusionValue('renderCurrentDocumentMetadata')) {
            return '';
        }

        return $this->contentElementWrappingService->wrapContentObject($node, $content, $this->getContentElementFusionPath(), $this->getAdditionalAttributes());
    }

    /**
     * Returns the Fusion path to the wrapped Content Element.
     *
     * @return string
     */
    protected function getContentElementFusionPath()
    {
        $fusionPathSegments = explode('/', $this->path);
        $numberOfFusionPathSegments = count($fusionPathSegments);
        if (isset($fusionPathSegments[$numberOfFusionPathSegments - 3])
            && $fusionPathSegments[$numberOfFusionPathSegments - 3] === '__meta'
            && isset($fusionPathSegments[$numberOfFusionPathSegments - 2])
            && $fusionPathSegments[$numberOfFusionPathSegments - 2] === 'process') {

            // cut off the SHORT processing syntax "__meta/process/contentElementWrapping<Neos.Neos:ContentElementWrapping>"
            return implode('/', array_slice($fusionPathSegments, 0, -3));
        }

        if (isset($fusionPathSegments[$numberOfFusionPathSegments - 4])
            && $fusionPathSegments[$numberOfFusionPathSegments - 4] === '__meta'
            && isset($fusionPathSegments[$numberOfFusionPathSegments - 3])
            && $fusionPathSegments[$numberOfFusionPathSegments - 3] === 'process') {

            // cut off the LONG processing syntax "__meta/process/contentElementWrapping/expression<Neos.Neos:ContentElementWrapping>"
            return implode('/', array_slice($fusionPathSegments, 0, -4));
        }

        return $this->path;
    }
}
