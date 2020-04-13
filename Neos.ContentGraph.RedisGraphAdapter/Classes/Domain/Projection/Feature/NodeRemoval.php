<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Domain\Projection\Feature;

use Doctrine\DBAL\Connection;
use Neos\ContentGraph\RedisGraphAdapter\Domain\Projection\HierarchyRelation;
use Neos\ContentGraph\RedisGraphAdapter\Domain\Repository\ProjectionContentGraph;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasRemoved;
use Neos\Flow\Log\SystemLoggerInterface;

/**
 * The NodeRemoval projection feature trait
 *
 * Requires RestrictionRelations to work
 */
trait NodeRemoval
{
    /**
     * @var ProjectionContentGraph
     */
    protected $projectionContentGraph;

    /**
     * @var SystemLoggerInterface
     */
    protected $systemLogger;

    /**
     * @param NodeAggregateWasRemoved $event
     * @throws \Throwable
     */
    public function whenNodeAggregateWasRemoved(NodeAggregateWasRemoved $event)
    {
        // the focus here is to be correct; that's why the method is not overly performant (for now at least). We might
        // lateron find tricks to improve performance
        $this->transactional(function () use ($event) {
            $this->removeOutgoingRestrictionRelationsOfNodeAggregateInDimensionSpacePoints(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getAffectedCoveredDimensionSpacePoints()
            );

            $ingoingRelations = $this->projectionContentGraph->findIngoingHierarchyRelationsForNodeAggregate(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getAffectedCoveredDimensionSpacePoints()
            );
            foreach ($ingoingRelations as $ingoingRelation) {
                $this->removeRelationRecursivelyFromDatabaseIncludingNonReferencedNodes($ingoingRelation);
            }
        });
    }

    /**
     * @param HierarchyRelation $ingoingRelation
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function removeRelationRecursivelyFromDatabaseIncludingNonReferencedNodes(HierarchyRelation $ingoingRelation)
    {
        $ingoingRelation->removeFromDatabase($this->getDatabaseConnection());

        foreach ($this->projectionContentGraph->findOutgoingHierarchyRelationsForNode($ingoingRelation->childNodeAnchor, $ingoingRelation->contentStreamIdentifier, new DimensionSpacePointSet([$ingoingRelation->dimensionSpacePoint])) as $outgoingRelation) {
            $this->removeRelationRecursivelyFromDatabaseIncludingNonReferencedNodes($outgoingRelation);
        }

        // remove node itself if it does not have any incoming hierarchy relations anymore
        $this->getDatabaseConnection()->executeUpdate('
            DELETE n FROM neos_contentgraph_node n
                LEFT JOIN
                    neos_contentgraph_hierarchyrelation h ON h.childnodeanchor = n.relationanchorpoint
                WHERE
                    n.relationanchorpoint = :anchorPointForNode
                    AND h.contentstreamidentifier IS NULL
                ',
            [
                'anchorPointForNode' => (string)$ingoingRelation->childNodeAnchor,
            ]
        );
    }

    abstract protected function getDatabaseConnection(): Connection;

    abstract protected function transactional(callable $operations): void;
}
