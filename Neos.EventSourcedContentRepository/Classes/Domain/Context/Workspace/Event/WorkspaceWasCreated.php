<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class WorkspaceWasCreated implements DomainEventInterface
{
    private WorkspaceName $workspaceName;

    private WorkspaceName $baseWorkspaceName;

    private WorkspaceTitle $workspaceTitle;

    private WorkspaceDescription $workspaceDescription;

    private UserIdentifier $initiatingUserIdentifier;

    private ContentStreamIdentifier $newContentStreamIdentifier;

    private ?UserIdentifier $workspaceOwner;

    public function __construct(
        WorkspaceName $workspaceName,
        WorkspaceName $baseWorkspaceName,
        WorkspaceTitle $workspaceTitle,
        WorkspaceDescription $workspaceDescription,
        UserIdentifier $initiatingUserIdentifier,
        ContentStreamIdentifier $newContentStreamIdentifier,
        UserIdentifier $workspaceOwner = null
    ) {
        $this->workspaceName = $workspaceName;
        $this->baseWorkspaceName = $baseWorkspaceName;
        $this->workspaceTitle = $workspaceTitle;
        $this->workspaceDescription = $workspaceDescription;
        $this->initiatingUserIdentifier = $initiatingUserIdentifier;
        $this->newContentStreamIdentifier = $newContentStreamIdentifier;
        $this->workspaceOwner = $workspaceOwner;
    }

    public function getWorkspaceName(): WorkspaceName
    {
        return $this->workspaceName;
    }

    public function getBaseWorkspaceName(): WorkspaceName
    {
        return $this->baseWorkspaceName;
    }

    public function getWorkspaceTitle(): WorkspaceTitle
    {
        return $this->workspaceTitle;
    }

    public function getWorkspaceDescription(): WorkspaceDescription
    {
        return $this->workspaceDescription;
    }

    public function getInitiatingUserIdentifier(): UserIdentifier
    {
        return $this->initiatingUserIdentifier;
    }

    public function getNewContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->newContentStreamIdentifier;
    }

    public function getWorkspaceOwner() : ?UserIdentifier
    {
        return $this->workspaceOwner;
    }
}
