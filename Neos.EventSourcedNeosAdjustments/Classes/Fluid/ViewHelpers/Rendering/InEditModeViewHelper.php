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

use Neos\ContentRepository\Intermediary\Domain\NodeBasedReadModelInterface;

/**
 * ViewHelper to find out if Neos is rendering an edit mode.
 *
 * = Examples =
 *
 * Given we are currently in an editing mode:
 *
 * <code title="Basic usage">
 * <f:if condition="{neos:rendering.inEditMode()}">
 *   <f:then>
 *     Shown for editing.
 *   </f:then>
 *   <f:else>
 *     Shown elsewhere (preview mode or not in backend).
 *   </f:else>
 * </f:if>
 * </code>
 * <output>
 * Shown for editing.
 * </output>
 *
 *
 * Given we are in the editing mode named "inPlace"
 *
 * <code title="Advanced usage">
 *
 * <f:if condition="{neos:rendering.inEditMode(mode: 'rawContent')}">
 *   <f:then>
 *     Shown just for rawContent editing mode.
 *   </f:then>
 *   <f:else>
 *     Shown in all other cases.
 *   </f:else>
 * </f:if>
 * </code>
 * <output>
 * Shown in all other cases.
 * </output>
 */
class InEditModeViewHelper extends AbstractRenderingStateViewHelper
{
    /**
     * @param NodeBasedReadModelInterface $node Optional Node to use context from
     * @param string $mode Optional rendering mode name to check if this specific mode is active
     * @return boolean
     */
    public function render(NodeBasedReadModelInterface $node = null, $mode = null)
    {
        // TODO: implement
        return false;
    }
}
