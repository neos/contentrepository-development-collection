<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Ui\Service;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\PublishWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\RebaseWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Projection\Changes\Change;
use Neos\EventSourcedContentRepository\Domain\Projection\Changes\ChangeFinder;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\TraversableNode;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\Workspace;

/**
 * A generic ContentRepository Publishing Service
 *
 * @api
 * @Flow\Scope("singleton")
 */
class PublishingService
{
    /**
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected ContentDimensionPresetSourceInterface $contentDimensionPresetSource;

    /**
     * @Flow\Inject
     * @var ContentGraphInterface
     */
    protected ContentGraphInterface $contentGraph;

    /**
     * @Flow\Inject
     * @var WorkspaceFinder
     */
    protected WorkspaceFinder $workspaceFinder;

    /**
     * @Flow\Inject
     * @var ChangeFinder
     */
    protected ChangeFinder $changeFinder;

    /**
     * @Flow\Inject
     * @var WorkspaceCommandHandler
     */
    protected WorkspaceCommandHandler $workspaceCommandHandler;

    /**
     * Returns a list of nodes contained in the given workspace which are not yet published
     *
     * @param WorkspaceName $workspaceName
     * @return TraversableNodeInterface[]
     * @api
     */
    public function getUnpublishedNodes(WorkspaceName $workspaceName)
    {
        $workspace = $this->workspaceFinder->findOneByName($workspaceName);
        if ($workspace->getBaseWorkspaceName() === null) {
            return [];
        }
        $changes = $this->changeFinder->findByContentStreamIdentifier($workspace->getCurrentContentStreamIdentifier());
        $unpublishedNodes = [];
        foreach ($changes as $change) {
            $subgraph = $this->contentGraph->getSubgraphByIdentifier(
                $workspace->getCurrentContentStreamIdentifier(),
                $change->originDimensionSpacePoint,
                VisibilityConstraints::withoutRestrictions()
            );
            $node = $subgraph->findNodeByNodeAggregateIdentifier($change->nodeAggregateIdentifier);

            if ($node instanceof TraversableNodeInterface) {
                $unpublishedNodes[] = new $node;
            }
        }
        return $unpublishedNodes;
    }

    /**
     * Returns the number of unpublished nodes contained in the given workspace
     *
     * @param Workspace $workspace
     * @return integer
     * @api
     */
    public function getUnpublishedNodesCount(Workspace $workspace)
    {
        $workspace = $this->workspaceFinder->findOneByName(new WorkspaceName($workspace->getName()));
        return $this->changeFinder->countByContentStreamIdentifier($workspace->getCurrentContentStreamIdentifier());
    }

    /**
     * @param WorkspaceName $workspaceName
     */
    public function publishWorkspace(WorkspaceName $workspaceName)
    {
        $command = new RebaseWorkspace(
            $workspaceName
        );

        // TODO: only rebase if necessary!
        $this->workspaceCommandHandler->handleRebaseWorkspace($command)->blockUntilProjectionsAreUpToDate();

        $command = new PublishWorkspace(
            $workspaceName
        );
        $this->workspaceCommandHandler->handlePublishWorkspace($command)->blockUntilProjectionsAreUpToDate();
    }
}
