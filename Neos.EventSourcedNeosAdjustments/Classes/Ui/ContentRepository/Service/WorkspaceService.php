<?php

namespace Neos\EventSourcedNeosAdjustments\Ui\ContentRepository\Service;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddressFactory;
use Neos\EventSourcedNeosAdjustments\Ui\Service\PublishingService;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\UserService as DomainUserService;
use Neos\Neos\Service\UserService;

/**
 * @Flow\Scope("singleton")
 */
class WorkspaceService
{
    /**
     * @Flow\Inject
     *
     * @var PublishingService
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     *
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     *
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     *
     * @var DomainUserService
     */
    protected $domainUserService;

    /**
     * @Flow\Inject
     *
     * @var NodeAddressFactory
     */
    protected $nodeAddressFactory;

    /**
     * Get all publishable node context paths for a workspace.
     *
     * @param Workspace $workspaceName
     *
     * @return array
     */
    public function getPublishableNodeInfo(WorkspaceName $workspaceName)
    {
        $publishableNodes = $this->publishingService->getUnpublishedNodes($workspaceName);

        $publishableNodes = array_map(function ($node) {
            $documentNode = (new FlowQuery([$node]))->closest('[instanceof Neos.Neos:Document]')->get(0);
            if ($documentNode) {
                return [
                    'contextPath'         => $this->nodeAddressFactory->createFromNode($node)->serializeForUri(),
                    'documentContextPath' => $this->nodeAddressFactory->createFromNode($documentNode)->serializeForUri(),
                ];
            }
        }, $publishableNodes);

        return array_values(array_filter($publishableNodes, function ($item) {
            return (bool) $item;
        }));
    }

    /**
     * Get allowed target workspaces for current user.
     *
     * @return array
     */
    public function getAllowedTargetWorkspaces()
    {
        $user = $this->domainUserService->getCurrentUser();

        $workspacesArray = [];
        /** @var Workspace $workspace */
        foreach ($this->workspaceRepository->findAll() as $workspace) {
            // FIXME: This check should be implemented through a specialized Workspace Privilege or something similar
            // Skip workspace not owned by current user
            if ($workspace->getOwner() !== null && $workspace->getOwner() !== $user) {
                continue;
            }
            // Skip own personal workspace
            if ($workspace === $this->userService->getPersonalWorkspace()) {
                continue;
            }

            if ($workspace->isPersonalWorkspace()) {
                // Skip other personal workspaces
                continue;
            }

            $workspaceArray = [
                'name'        => $workspace->getName(),
                'title'       => $workspace->getTitle(),
                'description' => $workspace->getDescription(),
                'readonly'    => !$this->domainUserService->currentUserCanPublishToWorkspace($workspace),
            ];
            $workspacesArray[$workspace->getName()] = $workspaceArray;
        }

        return $workspacesArray;
    }

    /**
     * Sets base workspace of current user workspace.
     *
     * @param Workspace $workspace
     *
     * @return void
     */
    public function setBaseWorkspace(Workspace $workspace)
    {
        $userWorkspace = $this->userService->getPersonalWorkspace();
        $userWorkspace->setBaseWorkspace($workspace);
    }
}
