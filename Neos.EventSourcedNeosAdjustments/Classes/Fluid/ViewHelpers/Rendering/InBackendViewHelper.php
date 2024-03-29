<?php
namespace Neos\EventSourcedNeosAdjustments\Fluid\ViewHelpers\Rendering;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;

/**
 * ViewHelper to find out if Neos is rendering the backend.
 *
 * = Examples =
 *
 * Given we are currently seeing the Neos backend:
 *
 * <code title="Basic usage">
 * <f:if condition="{neos:rendering.inBackend()}">
 *   <f:then>
 *     Shown in the backend.
 *   </f:then>
 *   <f:else>
 *     Shown when not in backend.
 *   </f:else>
 * </f:if>
 * </code>
 * <output>
 * Shown in the backend.
 * </output>
 */
class InBackendViewHelper extends AbstractRenderingStateViewHelper
{
    /**
     * Initialize the arguments.
     *
     * @return void
     * @throws \Neos\FluidAdaptor\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('node', NodeInterface::class, 'Node');
    }

    /**
     * @return boolean
     * @throws \Neos\FluidAdaptor\Core\ViewHelper\Exception
     */
    public function render()
    {
        $nodeAddress = $this->getNodeAddressOfContextNode($this->arguments['node']);
        return (!$nodeAddress->isInLiveWorkspace() && $this->hasAccessToBackend());
    }
}
