<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Ui\Domain\Model\Changes;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\MoveNodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\RelationDistributionStrategy;
use Neos\EventSourcedContentRepository\Service\Infrastructure\CommandBus\CommandBus;
use Neos\EventSourcedNeosAdjustments\Ui\Fusion\Helper\NodeInfoHelper;
use Neos\Flow\Annotations as Flow;

class MoveBefore extends AbstractMove
{

    /**
     * @Flow\Inject
     * @var CommandBus
     */
    protected $commandBus;

    /**
     * "Subject" is the to-be-moved node; the "sibling" node is the node after which the "Subject" should be copied.
     *
     * @return boolean
     */
    public function canApply()
    {
        $parent = $this->getSiblingNode()->findParentNode();
        $nodeType = $this->getSubject()->getNodeType();

        return NodeInfoHelper::isNodeTypeAllowedAsChildNode($parent, $nodeType);
    }

    public function getMode()
    {
        return 'before';
    }

    /**
     * Applies this change
     *
     * @return void
     */
    public function apply()
    {
        if ($this->canApply()) {
            // "subject" is the to-be-moved node
            $subject = $this->getSubject();
            $succeedingSibling = $this->getSiblingNode();

            $hasEqualParentNode = $subject->findParentNode()->getNodeAggregateIdentifier()->equals($succeedingSibling->findParentNode()->getNodeAggregateIdentifier());

            $command = new MoveNodeAggregate(
                $subject->getContentStreamIdentifier(),
                $subject->getDimensionSpacePoint(),
                $subject->getNodeAggregateIdentifier(),
                $hasEqualParentNode ? null : $succeedingSibling->findParentNode()->getNodeAggregateIdentifier(),
                $succeedingSibling->getNodeAggregateIdentifier(),
                RelationDistributionStrategy::gatherAll()
            );

            $this->contentCacheFlusher->registerNodeChange($subject);
            $this->commandBus->handleBlocking($command);

            $updateParentNodeInfo = new \Neos\EventSourcedNeosAdjustments\Ui\Domain\Model\Feedback\Operations\UpdateNodeInfo();
            $updateParentNodeInfo->setNode($succeedingSibling->findParentNode());

            $this->feedbackCollection->add($updateParentNodeInfo);

            $this->finish($subject);
        }
    }
}
