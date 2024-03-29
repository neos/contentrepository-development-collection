<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Fluid\ViewHelpers\Node;

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
use Neos\EventSourcedNeosAdjustments\Ui\ContentRepository\Service\NodeService;
use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;

/**
 * ViewHelper to find the closest document node to a given node
 */
class ClosestDocumentViewHelper extends AbstractViewHelper
{
    /**
     * @Flow\Inject
     * @var NodeService
     */
    protected $nodeService;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('node', NodeInterface::class, 'Node', true);
    }

    public function render(): ?NodeInterface
    {
        return $this->nodeService->getClosestDocument($this->arguments['node']);
    }
}
