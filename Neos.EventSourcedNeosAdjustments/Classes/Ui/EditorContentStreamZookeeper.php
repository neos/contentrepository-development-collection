<?php

namespace Neos\EventSourcedNeosAdjustments\Ui;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\RebaseWorkspace;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\WorkspaceCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\Workspace;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Authentication;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Flow\Security\Policy\Role;
use Neos\Neos\Domain\Model\User;
use Neos\Party\Domain\Service\PartyService;

/**
 * The service for keeping track of editors' content streams.
 *
 * On authentication, workspaces may have to be created and content streams may have to be forked from live or rebased from older ones
 *
 * @Flow\Scope("singleton")
 */
final class EditorContentStreamZookeeper
{
    /**
     * @Flow\Inject
     *
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     *
     * @var PartyService
     */
    protected $partyService;

    /**
     * @Flow\Inject
     *
     * @var PolicyService
     */
    protected $policyService;

    /**
     * @Flow\Inject
     *
     * @var WorkspaceFinder
     */
    protected $workspaceFinder;

    /**
     * @Flow\Inject
     *
     * @var WorkspaceCommandHandler
     */
    protected $workspaceCommandHandler;

    /**
     * This method is called whenever a login happens (AuthenticationProviderManager::class, 'authenticatedToken'), using
     * Signal/Slot.
     *
     * @param Authentication\TokenInterface $token
     *
     * @throws \Exception
     * @throws \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\BaseWorkspaceDoesNotExist
     * @throws \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\WorkspaceAlreadyExists
     * @throws \Neos\EventSourcedContentRepository\Domain\Context\Workspace\Exception\WorkspaceDoesNotExist
     * @throws \Neos\Flow\Persistence\Exception\InvalidQueryException
     */
    public function relayEditorAuthentication(Authentication\TokenInterface $token)
    {
        $isEditor = false;
        foreach ($token->getAccount()->getRoles() as $role) {
            /** @var Role $role */
            if (isset($role->getAllParentRoles()['Neos.Neos:AbstractEditor'])) {
                $isEditor = true;
                break;
            }
        }

        if ($isEditor) {
            $user = $this->partyService->getAssignedPartyOfAccount($token->getAccount());
            if ($user instanceof User) {
                /** @var Workspace $workspace */
                $workspace = $this->workspaceFinder->findOneByWorkspaceOwner($user);
                if (!$workspace) {
                    // @todo: find base workspace for user
                    $baseWorkspace = $this->workspaceFinder->findOneByName(WorkspaceName::forLive());
                    $userIdentifier = new UserIdentifier($this->persistenceManager->getIdentifierByObject($user));
                    $editorsNewContentStreamIdentifier = new ContentStreamIdentifier();
                    $workspaceName = \Neos\EventSourcedNeosAdjustments\Domain\Context\Workspace\WorkspaceName::fromAccountIdentifier($token->getAccount()->getAccountIdentifier());
                    $similarlyNamedWorkspaces = $this->workspaceFinder->findByPrefix($workspaceName->toContentRepositoryWorkspaceName());
                    if (!empty($similarlyNamedWorkspaces)) {
                        $workspaceName = $workspaceName->increment($similarlyNamedWorkspaces);
                    }

                    $this->workspaceCommandHandler->handleCreateWorkspace(new CreateWorkspace(
                        $workspaceName->toContentRepositoryWorkspaceName(),
                        $baseWorkspace->getWorkspaceName(),
                        new WorkspaceTitle((string) $user->getName()),
                        new WorkspaceDescription(''),
                        $userIdentifier,
                        $editorsNewContentStreamIdentifier,
                        $userIdentifier
                    ));
                } else {
                    $this->workspaceCommandHandler->handleRebaseWorkspace(new RebaseWorkspace(
                        $workspace->getWorkspaceName()
                    ));
                }
            }
        }
    }
}
