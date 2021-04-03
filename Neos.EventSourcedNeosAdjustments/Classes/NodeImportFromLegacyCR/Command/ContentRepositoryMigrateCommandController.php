<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\NodeImportFromLegacyCR\Command;

/*
 * This file is part of the Neos.ContentRepositoryMigration package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\GraphProjector;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\ContentDimensionZookeeper;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\InterDimensionalVariationGraph;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamRepository;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateEventPublisher;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\ContentStream\ContentStreamProjector;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceProjector;
use Neos\EventSourcedContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventSourcedNeosAdjustments\NodeImportFromLegacyCR\Service\ClosureEventPublisher;
use Neos\EventSourcedNeosAdjustments\NodeImportFromLegacyCR\Service\ContentRepositoryExportService;
use Neos\EventSourcing\EventListener\EventListenerInvoker;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\Storage\EventStorageInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * @Flow\Scope("singleton")
 */
class ContentRepositoryMigrateCommandController extends CommandController
{

    /**
     * @Flow\InjectConfiguration(path="EventStore.stores.ContentRepository", package="Neos.EventSourcing")
     * @var array
     */
    protected $eventStoreConfiguration;

    /**
     * @Flow\Inject(lazy=false)
     * @var EventNormalizer
     */
    protected $eventNormalizer;

    /**
     * @Flow\Inject(lazy=false)
     * @var GraphProjector
     */
    protected $graphProjector;

    /**
     * @Flow\Inject(lazy=false)
     * @var WorkspaceProjector
     */
    protected $workspaceProjector;

    /**
     * @Flow\Inject(lazy=false)
     * @var ContentStreamProjector
     */
    protected $contentStreamProjector;

    /**
     * @Flow\Inject(lazy=false)
     * @var ContentStreamRepository
     */
    protected $contentStreamRepository;

    /**
     * @Flow\Inject(lazy=false)
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject(lazy=false)
     * @var ContentDimensionZookeeper
     */
    protected $contentDimensionZookeeper;

    /**
     * @Flow\Inject(lazy=false)
     * @var ContentGraphInterface
     */
    protected $contentGraph;

    /**
     * @Flow\Inject(lazy=false)
     * @var InterDimensionalVariationGraph
     */
    protected $interDimensionalVariationGraph;

    /**
     * @Flow\Inject(lazy=false)
     * @var ReadSideMemoryCacheManager
     */
    protected $readSideMemoryCacheManager;

    /**
     * @var Connection
     */
    private $dbal;

    public function injectEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->dbal = $entityManager->getConnection();
    }

    /**
     * Run a CR export
     */
    public function runCommand()
    {
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        /** @var EventStorageInterface $eventStoreStorage */
        $eventStoreStorage = $this->objectManager->get($this->eventStoreConfiguration['storage'], $this->eventStoreConfiguration['storageOptions'] ?? []);

        // We need to build an own $eventStore instance, because we need a custom EventPublisher.
        $eventPublisher = new ClosureEventPublisher();
        $eventStore = new EventStore($eventStoreStorage, $eventPublisher, $this->eventNormalizer);

        // We also need to build our own NodeAggregateCommandHandler (and dependencies),
        // so that the custom $eventStore is used there.
        $nodeAggregateEventPublisher = new NodeAggregateEventPublisher(
            // this is the custom EventStore we need here
            $eventStore
        );

        $nodeAggregateCommandHandler = new NodeAggregateCommandHandler(
            $this->contentStreamRepository,
            $this->nodeTypeManager,
            $this->contentDimensionZookeeper,
            $this->contentGraph,
            $this->interDimensionalVariationGraph,
            // the nodeAggregateEventPublisher contains the custom EventStore from above
            $nodeAggregateEventPublisher,
            $this->readSideMemoryCacheManager
        );
        $contentRepositoryExportService = new ContentRepositoryExportService($eventStore, $nodeAggregateCommandHandler);

        $contentStreamProjectorInvoker = new EventListenerInvoker($eventStore, $this->contentStreamProjector, $this->dbal);
        $workspaceProjectorInvoker = new EventListenerInvoker($eventStore, $this->workspaceProjector, $this->dbal);
        $graphProjectorInvoker = new EventListenerInvoker($eventStore, $this->graphProjector, $this->dbal);

        $eventPublisher->setClosure(static function () use ($contentStreamProjectorInvoker, $workspaceProjectorInvoker, $graphProjectorInvoker) {
            $contentStreamProjectorInvoker->catchUp();
            $workspaceProjectorInvoker->catchUp();
            $graphProjectorInvoker->catchUp();
        });

        $contentRepositoryExportService->reset();
        $this->graphProjector->assumeProjectorRunsSynchronously();
        $this->workspaceProjector->assumeProjectorRunsSynchronously();
        $this->contentStreamProjector->assumeProjectorRunsSynchronously();

        $contentRepositoryExportService->migrate();

        // TODO: re-enable asynchronous behavior; and trigger catchup of all projections. (e.g. ChangeProjector etc)
        $this->outputLine('');
        $this->outputLine('');
        $this->outputLine('!!!!! NOW, run ./flow projection:replay change');
        $this->outputLine('!!!!! NOW, run ./flow projection:replay nodehiddenstate');
        $this->outputLine('!!!!! NOW, run ./flow projection:replay documenturipath');
    }
}
