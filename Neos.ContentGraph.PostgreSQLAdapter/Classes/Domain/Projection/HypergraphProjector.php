<?php
declare(strict_types=1);

namespace Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection;

/*
 * This file is part of the Neos.ContentGraph.PostgreSQLAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\ContentStreamForking;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeCreation;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeDisabling;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeModification;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeReferencing;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeRemoval;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeRenaming;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeTypeChange;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature\NodeVariation;
use Neos\ContentGraph\PostgreSQLAdapter\Infrastructure\DbalClient;
use Neos\EventSourcedContentRepository\Infrastructure\Projection\AbstractProcessedEventsAwareProjector;
use Neos\EventSourcedContentRepository\Service\Infrastructure\Service\DbalClient as EventStorageDbalClient;

/**
 * The alternate reality-aware hypergraph projector for the PostgreSQL backend via Doctrine DBAL
 */
final class HypergraphProjector extends AbstractProcessedEventsAwareProjector
{
    use ContentStreamForking;
    use NodeCreation;
    use NodeDisabling;
    use NodeModification;
    use NodeReferencing;
    use NodeRemoval;
    use NodeRenaming;
    use NodeTypeChange;
    use NodeVariation;

    private DbalClient $databaseClient;

    private ProjectionHypergraph $projectionHypergraph;

    public function __construct(
        DbalClient $databaseClient,
        EventStorageDbalClient $eventStorageDatabaseClient,
        VariableFrontend $processedEventsCache
    ) {
        $this->databaseClient = $databaseClient;
        $this->projectionHypergraph = new ProjectionHypergraph($databaseClient);
        parent::__construct($eventStorageDatabaseClient, $processedEventsCache);
    }

    /**
     * @throws \Throwable
     */
    public function reset(): void
    {
        parent::reset();
        $this->transactional(function () {
            $this->getDatabaseConnection()->executeQuery('TRUNCATE table ' . NodeRecord::TABLE_NAME);
            $this->getDatabaseConnection()->executeQuery('TRUNCATE table ' . HierarchyHyperrelationRecord::TABLE_NAME);
            $this->getDatabaseConnection()->executeQuery(
                'TRUNCATE table ' . RestrictionHyperrelationRecord::TABLE_NAME
            );
            $this->getDatabaseConnection()->executeQuery('TRUNCATE table ' . ReferenceHyperrelationRecord::TABLE_NAME);
        });
    }

    protected function getProjectionHypergraph(): ProjectionHypergraph
    {
        return $this->projectionHypergraph;
    }

    /**
     * @throws \Throwable
     */
    protected function transactional(\Closure $operations): void
    {
        $this->getDatabaseConnection()->transactional($operations);
    }

    protected function getDatabaseConnection(): Connection
    {
        return $this->databaseClient->getConnection();
    }
}
