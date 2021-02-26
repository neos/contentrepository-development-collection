<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Projection\Workspace;

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
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\RootWorkspaceWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceRebaseFailed;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasDiscarded;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasPartiallyDiscarded;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasPartiallyPublished;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasPublished;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\WorkspaceWasRebased;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Infrastructure\Projection\AbstractProcessedEventsAwareProjector;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class WorkspaceProjector extends AbstractProcessedEventsAwareProjector
{
    private const TABLE_NAME = 'neos_contentrepository_projection_workspace_v1';

    public function whenWorkspaceWasCreated(WorkspaceWasCreated $event)
    {
        $this->getDatabaseConnection()->insert(self::TABLE_NAME, [
            'workspaceName' => $event->getWorkspaceName(),
            'baseWorkspaceName' => $event->getBaseWorkspaceName(),
            'workspaceTitle' => $event->getWorkspaceTitle(),
            'workspaceDescription' => $event->getWorkspaceDescription(),
            'workspaceOwner' => $event->getWorkspaceOwner(),
            'currentContentStreamIdentifier' => $event->getNewContentStreamIdentifier(),
            'status' => Workspace::STATUS_UP_TO_DATE
        ]);
    }

    public function whenRootWorkspaceWasCreated(RootWorkspaceWasCreated $event)
    {
        $this->getDatabaseConnection()->insert(self::TABLE_NAME, [
            'workspaceName' => $event->getWorkspaceName(),
            'workspaceTitle' => $event->getWorkspaceTitle(),
            'workspaceDescription' => $event->getWorkspaceDescription(),
            'currentContentStreamIdentifier' => $event->getNewContentStreamIdentifier(),
            'status' => Workspace::STATUS_UP_TO_DATE
        ]);
    }

    public function whenWorkspaceWasDiscarded(WorkspaceWasDiscarded $event)
    {
        $this->updateContentStreamIdentifier($event->getNewContentStreamIdentifier(), $event->getWorkspaceName());
        $this->markDependentWorkspacesAsOutdated($event->getWorkspaceName());
    }

    public function whenWorkspaceWasPartiallyDiscarded(WorkspaceWasPartiallyDiscarded $event)
    {
        $this->updateContentStreamIdentifier($event->getNewContentStreamIdentifier(), $event->getWorkspaceName());
        $this->markDependentWorkspacesAsOutdated($event->getWorkspaceName());
    }

    public function whenWorkspaceWasPartiallyPublished(WorkspaceWasPartiallyPublished $event)
    {
        // TODO: How do we test this method? It's hard to design a BDD testcase failing if this method is commented out...
        $this->updateContentStreamIdentifier($event->getNewSourceContentStreamIdentifier(), $event->getSourceWorkspaceName());

        $this->markDependentWorkspacesAsOutdated($event->getTargetWorkspaceName());

        // NASTY: we need to set the source workspace name as non-outdated; as it has been made up-to-date again.
        $this->markWorkspaceAsUpToDate($event->getSourceWorkspaceName());

        $this->markDependentWorkspacesAsOutdated($event->getSourceWorkspaceName());
    }

    public function whenWorkspaceWasPublished(WorkspaceWasPublished $event)
    {
        // TODO: How do we test this method? It's hard to design a BDD testcase failing if this method is commented out...
        $this->updateContentStreamIdentifier($event->getNewSourceContentStreamIdentifier(), $event->getSourceWorkspaceName());

        $this->markDependentWorkspacesAsOutdated($event->getTargetWorkspaceName());

        // NASTY: we need to set the source workspace name as non-outdated; as it has been made up-to-date again.
        $this->markWorkspaceAsUpToDate($event->getSourceWorkspaceName());

        $this->markDependentWorkspacesAsOutdated($event->getSourceWorkspaceName());
    }

    public function whenWorkspaceWasRebased(WorkspaceWasRebased $event)
    {
        $this->updateContentStreamIdentifier($event->getNewContentStreamIdentifier(), $event->getWorkspaceName());
        $this->markDependentWorkspacesAsOutdated($event->getWorkspaceName());

        // When the rebase is successful, we can set the status of the workspace back to UP_TO_DATE.
        $this->markWorkspaceAsUpToDate($event->getWorkspaceName());
    }

    public function whenWorkspaceRebaseFailed(WorkspaceRebaseFailed $event)
    {
        $this->markWorkspaceAsOutdatedConflict($event->getWorkspaceName());
    }

    private function updateContentStreamIdentifier(ContentStreamIdentifier $contentStreamIdentifier, WorkspaceName $workspaceName): void
    {
        $this->getDatabaseConnection()->update(self::TABLE_NAME, [
            'currentContentStreamIdentifier' => $contentStreamIdentifier
        ], [
            'workspaceName' => $workspaceName
        ]);
    }

    private function markWorkspaceAsUpToDate(WorkspaceName $workspaceName): void
    {
        $this->getDatabaseConnection()->executeUpdate('
            UPDATE neos_contentrepository_projection_workspace_v1
            SET status = :upToDate
            WHERE
                workspacename = :workspaceName
        ', [
            'upToDate' => Workspace::STATUS_UP_TO_DATE,
            'workspaceName' => $workspaceName->jsonSerialize()
        ]);
    }

    private function markDependentWorkspacesAsOutdated(WorkspaceName $baseWorkspaceName): void
    {
        $this->getDatabaseConnection()->executeUpdate('
            UPDATE neos_contentrepository_projection_workspace_v1
            SET status = :outdated
            WHERE
                baseworkspacename = :baseWorkspaceName
        ', [
            'outdated' => Workspace::STATUS_OUTDATED,
            'baseWorkspaceName' => $baseWorkspaceName->jsonSerialize()
        ]);
    }

    private function markWorkspaceAsOutdatedConflict(WorkspaceName $workspaceName): void
    {
        $this->getDatabaseConnection()->executeUpdate('
            UPDATE neos_contentrepository_projection_workspace_v1
            SET
                status = :outdatedConflict,
                foo = bar
            WHERE
                workspacename = :workspaceName
        ', [
            'outdatedConflict' => Workspace::STATUS_OUTDATED_CONFLICT,
            'workspaceName' => $workspaceName->jsonSerialize()
        ]);
    }

    public function reset(): void
    {
        parent::reset();
        $this->getDatabaseConnection()->exec('TRUNCATE ' . self::TABLE_NAME);
    }
}
