<?php

namespace Neos\StandaloneCrExample;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\CreateRootNodeAggregateWithNode;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Command\CreateRootWorkspace;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcedContentRepository\Standalone\Configuration\ContentRepositoryConfiguration;
use Neos\EventSourcedContentRepository\Standalone\ContentRepository;

class Example1
{
    public static function run(ContentRepositoryConfiguration $contentRepositoryConfiguration)
    {
        $contentRepository = ContentRepository::create($contentRepositoryConfiguration);
        $contentRepository->truncateAllTables();
        $contentRepository->migrate();

        $contentRepository->runSynchronously();
        $contentRepository->getEventStore()->setup();

        $cs = ContentStreamIdentifier::create();

        $createRootWorkspace = new CreateRootWorkspace(
            WorkspaceName::forLive(),
            new WorkspaceTitle('live'),
            new WorkspaceDescription('The live WS'),
            UserIdentifier::forSystemUser(),
            $cs
        );

        $cmd = $contentRepository->getWorkspaceCommandHandler();
        $cmd->handleCreateRootWorkspace($createRootWorkspace);

        $createRootNode = new CreateRootNodeAggregateWithNode(
            $cs,
            NodeAggregateIdentifier::create(),
            NodeTypeName::fromString('Neos.ContentRepository:Root'),
            UserIdentifier::forSystemUser()
        );
        $cmd = $contentRepository->getNodeAggregateCommandHandler();
        $cmd->handleCreateRootNodeAggregateWithNode($createRootNode);

        $createChildNode = new CreateNodeAggregateWithNode(
            $cs,
            NodeAggregateIdentifier::create(),
            NodeTypeName::fromString('Example:Foo'),
            DimensionSpacePoint::fromArray([]),
            UserIdentifier::forSystemUser(),
            $createRootNode->getNodeAggregateIdentifier()
        );
        $cmd = $contentRepository->getNodeAggregateCommandHandler();
        $cmd->handleCreateNodeAggregateWithNode($createChildNode);
    }
}
