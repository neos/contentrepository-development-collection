<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Domain\Projection;

/*
 * This file is part of the Neos.ContentGraph.RedisGraphAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Neos\ContentGraph\RedisGraphAdapter\Domain\Projection\Feature\NodeMove;
use Neos\ContentGraph\RedisGraphAdapter\Domain\Projection\Feature\NodeRemoval;
use Neos\ContentGraph\RedisGraphAdapter\Domain\Projection\Feature\RestrictionRelations;
use Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\Graph;
use Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain as ContentRepository;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasDisabled;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasEnabled;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeGeneralizationVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodePropertiesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeReferencesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeSpecializationVariantWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\RootNodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\EventSourcedContentRepository\Infrastructure\Projection\AbstractProcessedEventsAwareProjector;
use Neos\EventSourcedContentRepository\Infrastructure\Projection\ProcessedEventsAwareProjectorInterface;
use Neos\Flow\Annotations as Flow;

/**
 * The alternate reality-aware graph projector for the Doctrine backend
 *
 * @Flow\Scope("singleton")
 */
class RedisGraphProjector extends AbstractProcessedEventsAwareProjector
{
    use RestrictionRelations;
    use NodeRemoval;
    use NodeMove;

    /**
     * @Flow\Inject
     * @var RedisClient
     */
    protected $redisClient;

    const RELATION_DEFAULT_OFFSET = 128;

    /**
     * @throws \Throwable
     */
    public function reset(): void
    {
        $this->redisClient->getRedisClient()->flushDB();
    }

    /**
     * @param RootNodeAggregateWithNodeWasCreated $event
     * @throws \Throwable
     */
    final public function whenRootNodeAggregateWithNodeWasCreated(RootNodeAggregateWithNodeWasCreated $event)
    {
        $dimensionSpacePoint = new DimensionSpacePoint([]);
        $node = new NodeRecord(
            $event->getNodeAggregateIdentifier(),
            $dimensionSpacePoint->getCoordinates(),
            $dimensionSpacePoint->getHash(),
            [],
            $event->getNodeTypeName(),
            $event->getNodeAggregateClassification()
        );

        $this->redisClient->transactionalForContentStream($event->getContentStreamIdentifier(), function (Graph $graph) use ($node, $event) {

            // RootNodeParent should exist only one; thus we use MERGE.
            $graph->execute("MERGE (:RootNodeParent)");

            // CREATE the root node.
            $graph->execute("CREATE (:Node {$node->toCypherProperties()})");

            $this->connectHierarchy(
                $graph,
                'MATCH (parent:RootNodeParent)',
                "MATCH (child:Node {$node->nodeAggregateIdentifierToCypherProperties()})",
                $event->getCoveredDimensionSpacePoints(),
                null
            );

        });
    }


    /**
     * @param Graph $graph
     * @param GraphNode $parentNodeAnchorPoint
     * @param GraphNode $childNodeAnchorPoint
     * @param DimensionSpacePointSet $dimensionSpacePointSet
     * @param NodeRelationAnchorPoint|null $succeedingSiblingNodeAnchorPoint
     * @param NodeName|null $relationName
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function connectHierarchy(
        Graph $graph,
        $parentNodeQuery,
        $childNodeQuery,
        DimensionSpacePointSet $dimensionSpacePointSet,
        ?NodeRelationAnchorPoint $succeedingSiblingNodeAnchorPoint,
        NodeName $relationName = null
    ): void
    {
        foreach ($dimensionSpacePointSet->getPoints() as $dimensionSpacePoint) {
            // TODO:
            /*$position = $this->getRelationPosition(
                $parentNodeAnchorPoint,
                null,
                $succeedingSiblingNodeAnchorPoint,
                $contentStreamIdentifier,
                $dimensionSpacePoint
            );*/

            $hierarchyRelation = new HierarchyRelation(
                $relationName,
                $dimensionSpacePoint,
                $dimensionSpacePoint->getHash(),
                0, //TODO: $position
            );
            $command = $parentNodeQuery . "\n" . $childNodeQuery . "\n" .
                "CREATE (parent) -[:HIERARCHY {$hierarchyRelation->toCypherProperties()}]-> (child)";
            $graph->execute($command);
        }
    }


    /**
     * @param Event\NodeAggregateWithNodeWasCreated $event
     * @throws \Throwable
     */
    final public function whenNodeAggregateWithNodeWasCreated(Event\NodeAggregateWithNodeWasCreated $event)
    {
        $this->redisClient->transactionalForContentStream($event->getContentStreamIdentifier(), function (Graph $graph) use ($event) {
            $this->createNodeWithHierarchy(
                $graph,
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getNodeTypeName(),
                $event->getParentNodeAggregateIdentifier(),
                $event->getOriginDimensionSpacePoint(),
                $event->getCoveredDimensionSpacePoints(),
                $event->getInitialPropertyValues(),
                $event->getNodeAggregateClassification(),
                $event->getSucceedingNodeAggregateIdentifier(),
                $event->getNodeName()
            );

            // TODO: Restriction relations
            /*$this->connectRestrictionRelationsFromParentNodeToNewlyCreatedNode(
                $event->getContentStreamIdentifier(),
                $event->getParentNodeAggregateIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getCoveredDimensionSpacePoints()
            );*/
        });
    }

    /**
     * @param Event\NodeAggregateNameWasChanged $event
     * @throws \Throwable
     */
    final public function whenNodeAggregateNameWasChanged(Event\NodeAggregateNameWasChanged $event)
    {
        $this->transactional(function () use ($event) {
            $this->getDatabaseConnection()->executeUpdate('
                UPDATE neos_contentgraph_hierarchyrelation h
                inner join neos_contentgraph_node n on
                    h.childnodeanchor = n.relationanchorpoint
                SET
                  h.name = :newName
                WHERE
                    n.nodeaggregateidentifier = :nodeAggregateIdentifier
                    and h.contentstreamidentifier = :contentStreamIdentifier
            ', [
                'newName' => (string)$event->getNewNodeName(),
                'nodeAggregateIdentifier' => (string)$event->getNodeAggregateIdentifier(),
                'contentStreamIdentifier' => (string)$event->getContentStreamIdentifier()
            ]);
        });
    }

    /**
     * Copy the restriction edges from the parent Node to the newly created child node;
     * so that newly created nodes inherit the visibility constraints of the parent.
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param NodeAggregateIdentifier $newlyCreatedNodeAggregateIdentifier
     * @param DimensionSpacePointSet $dimensionSpacePointsInWhichNewlyCreatedNodeAggregateIsVisible
     * @throws \Doctrine\DBAL\DBALException
     */
    private function connectRestrictionRelationsFromParentNodeToNewlyCreatedNode(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeAggregateIdentifier $newlyCreatedNodeAggregateIdentifier,
        DimensionSpacePointSet $dimensionSpacePointsInWhichNewlyCreatedNodeAggregateIsVisible
    )
    {
        // TODO: still unsure why we need an "INSERT IGNORE" here; normal "INSERT" can trigger a duplicate key constraint exception
        $this->getDatabaseConnection()->executeUpdate('
                INSERT IGNORE INTO neos_contentgraph_restrictionrelation (
                  contentstreamidentifier,
                  dimensionspacepointhash,
                  originnodeaggregateidentifier,
                  affectednodeaggregateidentifier
                )
                SELECT
                  r.contentstreamidentifier,
                  r.dimensionspacepointhash,
                  r.originnodeaggregateidentifier,
                  "' . $newlyCreatedNodeAggregateIdentifier . '" as affectednodeaggregateidentifier
                FROM
                    neos_contentgraph_restrictionrelation r
                    WHERE
                        r.contentstreamidentifier = :sourceContentStreamIdentifier
                        and r.dimensionspacepointhash IN (:visibleDimensionSpacePoints)
                        and r.affectednodeaggregateidentifier = :parentNodeAggregateIdentifier
            ', [
            'sourceContentStreamIdentifier' => (string)$contentStreamIdentifier,
            'visibleDimensionSpacePoints' => $dimensionSpacePointsInWhichNewlyCreatedNodeAggregateIsVisible->getPointHashes(),
            'parentNodeAggregateIdentifier' => (string)$parentNodeAggregateIdentifier
        ], [
            'visibleDimensionSpacePoints' => Connection::PARAM_STR_ARRAY
        ]);
    }

    /**
     * @param Graph $graph
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param NodeTypeName $nodeTypeName
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param DimensionSpacePoint $originDimensionSpacePoint
     * @param DimensionSpacePointSet $visibleInDimensionSpacePoints
     * @param PropertyValues $propertyDefaultValuesAndTypes
     * @param NodeAggregateClassification $nodeAggregateClassification
     * @param NodeAggregateIdentifier|null $succeedingSiblingNodeAggregateIdentifier
     * @param NodeName $nodeName
     * @throws \Doctrine\DBAL\DBALException
     */
    private function createNodeWithHierarchy(
        Graph $graph,
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeName $nodeTypeName,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        DimensionSpacePoint $originDimensionSpacePoint,
        DimensionSpacePointSet $visibleInDimensionSpacePoints,
        PropertyValues $propertyDefaultValuesAndTypes,
        NodeAggregateClassification $nodeAggregateClassification,
        NodeAggregateIdentifier $succeedingSiblingNodeAggregateIdentifier = null,
        NodeName $nodeName = null
    ): void
    {
        $node = new NodeRecord(
            $nodeAggregateIdentifier,
            $originDimensionSpacePoint->jsonSerialize(),
            $originDimensionSpacePoint->getHash(),
            $propertyDefaultValuesAndTypes->getPlainValues(),
            $nodeTypeName,
            $nodeAggregateClassification,
            $nodeName
        );

        $matchQueryParts = [];
        $createQueryParts = [];

        $graph->execute("CREATE (:Node {$node->toCypherProperties()})");

        // reconnect parent relations
        $missingParentRelations = $visibleInDimensionSpacePoints->getPoints();

        if (!empty($missingParentRelations)) {
            // add yet missing parent relations

            foreach ($missingParentRelations as $i => $dimensionSpacePoint) {

                // TODO: SUCCEEDING SIBLING

                /*$succeedingSibling = $succeedingSiblingNodeAggregateIdentifier
                    ? $this->projectionContentGraph->findNodeInAggregate(
                        $contentStreamIdentifier,
                        $succeedingSiblingNodeAggregateIdentifier,
                        $dimensionSpacePoint
                    )
                    : null;*/

                $this->connectHierarchy(
                    $graph,
                    "
                        MATCH
                            ()
                            -[:HIERARCHY {dimensionSpacePointHash: '{$dimensionSpacePoint->getHash()}'}]->
                            (parent:Node {nodeAggregateIdentifier: '{$parentNodeAggregateIdentifier->jsonSerialize()}'})
                    ",
                    "
                        MATCH
                            (child:Node {$node->nodeAggregateIdentifierToCypherProperties()})
                    ",
                    // TODO: looks strange, we can probably change this....
                    new DimensionSpacePointSet([$dimensionSpacePoint]),
                    null,
                    $nodeName
                );
            }
        }
    }

    /**
     * @param NodeRelationAnchorPoint|null $parentAnchorPoint
     * @param NodeRelationAnchorPoint|null $childAnchorPoint
     * @param NodeRelationAnchorPoint|null $succeedingSiblingAnchorPoint
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getRelationPosition(
        ?NodeRelationAnchorPoint $parentAnchorPoint,
        ?NodeRelationAnchorPoint $childAnchorPoint,
        ?NodeRelationAnchorPoint $succeedingSiblingAnchorPoint,
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint
    ): int
    {
        $position = $this->projectionContentGraph->determineHierarchyRelationPosition($parentAnchorPoint, $childAnchorPoint, $succeedingSiblingAnchorPoint, $contentStreamIdentifier, $dimensionSpacePoint);

        if ($position % 2 !== 0) {
            $position = $this->getRelationPositionAfterRecalculation($parentAnchorPoint, $childAnchorPoint, $succeedingSiblingAnchorPoint, $contentStreamIdentifier, $dimensionSpacePoint);
        }

        return $position;
    }

    /**
     * @param NodeRelationAnchorPoint|null $parentAnchorPoint
     * @param NodeRelationAnchorPoint|null $childAnchorPoint
     * @param NodeRelationAnchorPoint|null $succeedingSiblingAnchorPoint
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getRelationPositionAfterRecalculation(
        ?NodeRelationAnchorPoint $parentAnchorPoint,
        ?NodeRelationAnchorPoint $childAnchorPoint,
        ?NodeRelationAnchorPoint $succeedingSiblingAnchorPoint,
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint
    ): int
    {
        if (!$childAnchorPoint && !$parentAnchorPoint) {
            throw new \InvalidArgumentException('You must either specify a parent or child node anchor to get relation positions after recalculation.', 1519847858);
        }
        $offset = 0;
        $position = 0;
        $hierarchyRelations = $parentAnchorPoint
            ? $this->projectionContentGraph->getOutgoingHierarchyRelationsForNodeAndSubgraph($parentAnchorPoint, $contentStreamIdentifier, $dimensionSpacePoint)
            : $this->projectionContentGraph->getIngoingHierarchyRelationsForNodeAndSubgraph($childAnchorPoint, $contentStreamIdentifier, $dimensionSpacePoint);

        foreach ($hierarchyRelations as $relation) {
            $offset += self::RELATION_DEFAULT_OFFSET;
            if ($succeedingSiblingAnchorPoint && $relation->childNodeAnchor === (string)$succeedingSiblingAnchorPoint) {
                $position = $offset;
                $offset += self::RELATION_DEFAULT_OFFSET;
            }
            $relation->assignNewPosition($offset, $this->getDatabaseConnection());
        }

        return $position;
    }

    /**
     * @param ContentRepository\Context\ContentStream\Event\ContentStreamWasForked $event
     * @throws \Throwable
     */
    public function whenContentStreamWasForked(ContentRepository\Context\ContentStream\Event\ContentStreamWasForked $event)
    {
        $this->transactional(function () use ($event) {

            //
            // 1) Copy HIERARCHY RELATIONS (this is the MAIN OPERATION here)
            //
            $this->getDatabaseConnection()->executeUpdate('
                INSERT INTO neos_contentgraph_hierarchyrelation (
                  parentnodeanchor,
                  childnodeanchor,
                  `name`,
                  position,
                  dimensionspacepoint,
                  dimensionspacepointhash,
                  contentstreamidentifier
                )
                SELECT
                  h.parentnodeanchor,
                  h.childnodeanchor,
                  h.name,
                  h.position,
                  h.dimensionspacepoint,
                  h.dimensionspacepointhash,
                  "' . (string)$event->getContentStreamIdentifier() . '" AS contentstreamidentifier
                FROM
                    neos_contentgraph_hierarchyrelation h
                    WHERE h.contentstreamidentifier = :sourceContentStreamIdentifier
            ', [
                'sourceContentStreamIdentifier' => (string)$event->getSourceContentStreamIdentifier()
            ]);

            //
            // 2) copy Hidden Node information to second content stream
            //
            $this->getDatabaseConnection()->executeUpdate('
                INSERT INTO neos_contentgraph_restrictionrelation (
                  contentstreamidentifier,
                  dimensionspacepointhash,
                  originnodeaggregateidentifier,
                  affectednodeaggregateidentifier
                )
                SELECT
                  "' . (string)$event->getContentStreamIdentifier() . '" AS contentstreamidentifier,
                  r.dimensionspacepointhash,
                  r.originnodeaggregateidentifier,
                  r.affectednodeaggregateidentifier
                FROM
                    neos_contentgraph_restrictionrelation r
                    WHERE r.contentstreamidentifier = :sourceContentStreamIdentifier
            ', [
                'sourceContentStreamIdentifier' => (string)$event->getSourceContentStreamIdentifier()
            ]);
        });
    }

    public function whenContentStreamWasRemoved(ContentRepository\Context\ContentStream\Event\ContentStreamWasRemoved $event)
    {
        $this->transactional(function () use ($event) {

            // Drop hierarchy relations
            $this->getDatabaseConnection()->executeUpdate('
                DELETE FROM neos_contentgraph_hierarchyrelation
                WHERE
                    contentstreamidentifier = :contentStreamIdentifier
            ', [
                'contentStreamIdentifier' => (string)$event->getContentStreamIdentifier()
            ]);

            // Drop non-referenced nodes (which do not have a hierarchy relation anymore)
            $this->getDatabaseConnection()->executeUpdate('
                DELETE FROM neos_contentgraph_node
                WHERE NOT EXISTS
                    (
                        SELECT 1 FROM neos_contentgraph_hierarchyrelation
                        WHERE neos_contentgraph_hierarchyrelation.childnodeanchor = neos_contentgraph_node.relationanchorpoint
                    )
            ');

            // Drop non-referenced reference relations (i.e. because the referenced nodes are gone by now)
            $this->getDatabaseConnection()->executeUpdate('
                DELETE FROM neos_contentgraph_referencerelation
                WHERE NOT EXISTS
                    (
                        SELECT 1 FROM neos_contentgraph_node
                        WHERE neos_contentgraph_node.relationanchorpoint = neos_contentgraph_referencerelation.nodeanchorpoint
                    )
            ');

            // Drop restriction relations
            $this->getDatabaseConnection()->executeUpdate('
                DELETE FROM neos_contentgraph_restrictionrelation
                WHERE
                    contentstreamidentifier = :contentStreamIdentifier
            ', [
                'contentStreamIdentifier' => (string)$event->getContentStreamIdentifier()
            ]);
        });
    }

    /**
     * @param NodePropertiesWereSet $event
     * @throws \Throwable
     */
    public function whenNodePropertiesWereSet(NodePropertiesWereSet $event)
    {
        $this->transactional(function () use ($event) {
            $this->updateNodeWithCopyOnWrite($event, function (NodeRecord $node) use ($event) {
                foreach ($event->getPropertyValues() as $propertyName => $propertyValue) {
                    $node->properties[$propertyName] = $propertyValue->getValue();
                }
            });
        });
    }

    /**
     * @param NodeReferencesWereSet $event
     * @throws \Throwable
     */
    public function whenNodeReferencesWereSet(NodeReferencesWereSet $event)
    {
        // TODO: implement references later.
        return;
        $this->transactional(function () use ($event) {
            $nodeAnchorPoint = $this->projectionContentGraph->getAnchorPointForNodeAndOriginDimensionSpacePointAndContentStream(
                $event->getSourceNodeAggregateIdentifier(),
                $event->getSourceOriginDimensionSpacePoint(),
                $event->getContentStreamIdentifier()
            );

            // remove old
            $this->getDatabaseConnection()->delete('neos_contentgraph_referencerelation', [
                'nodeanchorpoint' => $nodeAnchorPoint,
                'name' => $event->getReferenceName()
            ]);

            // set new
            foreach ($event->getDestinationNodeAggregateIdentifiers() as $position => $destinationNodeIdentifier) {
                $this->getDatabaseConnection()->insert('neos_contentgraph_referencerelation', [
                    'name' => $event->getReferenceName(),
                    'position' => $position,
                    'nodeanchorpoint' => $nodeAnchorPoint,
                    'destinationnodeaggregateidentifier' => $destinationNodeIdentifier,
                ]);
            }
        });
    }

    /**
     * @param NodeAggregateWasDisabled $event
     * @throws \Throwable
     */
    public function whenNodeAggregateWasDisabled(NodeAggregateWasDisabled $event)
    {
        $this->transactional(function () use ($event) {
            // TODO: still unsure why we need an "INSERT IGNORE" here; normal "INSERT" can trigger a duplicate key constraint exception
            $this->getDatabaseConnection()->executeUpdate('
-- GraphProjector::whenNodeAggregateWasDisabled
insert ignore into neos_contentgraph_restrictionrelation
(
    -- we build a recursive tree
    with recursive tree as (
         -- --------------------------------
         -- INITIAL query: select the root nodes of the tree; as given in $menuLevelNodeIdentifiers
         -- --------------------------------
         select
            n.relationanchorpoint,
            n.nodeaggregateidentifier,
            h.dimensionspacepointhash
         from
            neos_contentgraph_node n
         -- we need to join with the hierarchy relation, because we need the dimensionspacepointhash.
         inner join neos_contentgraph_hierarchyrelation h
            on h.childnodeanchor = n.relationanchorpoint
         where
            n.nodeaggregateidentifier = :entryNodeAggregateIdentifier
            and h.contentstreamidentifier = :contentStreamIdentifier
            and h.dimensionspacepointhash in (:dimensionSpacePointHashes)
    union
         -- --------------------------------
         -- RECURSIVE query: do one "child" query step
         -- --------------------------------
         select
            c.relationanchorpoint,
            c.nodeaggregateidentifier,
            h.dimensionspacepointhash
         from
            tree p
         inner join neos_contentgraph_hierarchyrelation h
            on h.parentnodeanchor = p.relationanchorpoint
         inner join neos_contentgraph_node c
            on h.childnodeanchor = c.relationanchorpoint
         where
            h.contentstreamidentifier = :contentStreamIdentifier
            and h.dimensionspacepointhash in (:dimensionSpacePointHashes)
    )

    select
        "' . (string)$event->getContentStreamIdentifier() . '" as contentstreamidentifier,
        dimensionspacepointhash,
        "' . (string)$event->getNodeAggregateIdentifier() . '" as originnodeidentifier,
        nodeaggregateidentifier as affectednodeaggregateidentifier
    from tree
)
            ',
                [
                    'entryNodeAggregateIdentifier' => (string)$event->getNodeAggregateIdentifier(),
                    'contentStreamIdentifier' => (string)$event->getContentStreamIdentifier(),
                    'dimensionSpacePointHashes' => $event->getAffectedDimensionSpacePoints()->getPointHashes()
                ],
                [
                    'dimensionSpacePointHashes' => Connection::PARAM_STR_ARRAY
                ]);
        });
    }

    private function cascadeRestrictionRelations(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        NodeAggregateIdentifier $entryNodeAggregateIdentifier,
        DimensionSpacePointSet $affectedDimensionSpacePoints
    ): void
    {
        $this->getDatabaseConnection()->executeUpdate('
            -- GraphProjector::cascadeRestrictionRelations
            INSERT INTO neos_contentgraph_restrictionrelation
            (
                -- we build a recursive tree
                with recursive tree as (
                     -- --------------------------------
                     -- INITIAL query: select the nodes of the given entry node aggregate as roots of the tree
                     -- --------------------------------
                     select
                        n.relationanchorpoint,
                        n.nodeaggregateidentifier,
                        h.dimensionspacepointhash
                     from
                        neos_contentgraph_node n
                     -- we need to join with the hierarchy relation, because we need the dimensionspacepointhash.
                     inner join neos_contentgraph_hierarchyrelation h
                        on h.childnodeanchor = n.relationanchorpoint
                     where
                        n.nodeaggregateidentifier = :entryNodeAggregateIdentifier
                        and h.contentstreamidentifier = :contentStreamIdentifier
                        and h.dimensionspacepointhash in (:dimensionSpacePointHashes)
                union
                     -- --------------------------------
                     -- RECURSIVE query: do one "child" query step
                     -- --------------------------------
                     select
                        c.relationanchorpoint,
                        c.nodeaggregateidentifier,
                        h.dimensionspacepointhash
                     from
                        tree p
                     inner join neos_contentgraph_hierarchyrelation h
                        on h.parentnodeanchor = p.relationanchorpoint
                     inner join neos_contentgraph_node c
                        on h.childnodeanchor = c.relationanchorpoint
                     where
                        h.contentstreamidentifier = :contentStreamIdentifier
                        and h.dimensionspacepointhash in (:dimensionSpacePointHashes)
                )

                     -- --------------------------------
                     -- create new restriction relations...
                     -- --------------------------------
                SELECT
                    "' . (string)$contentStreamIdentifier . '" as contentstreamidentifier,
                    tree.dimensionspacepointhash,
                    originnodeaggregateidentifier,
                    tree.nodeaggregateidentifier as affectednodeaggregateidentifier
                FROM tree
                     -- --------------------------------
                     -- ...by joining the tree with all restriction relations ingoing to the given parent
                     -- --------------------------------
                    INNER JOIN (
                        SELECT originnodeaggregateidentifier FROM neos_contentgraph_restrictionrelation
                            WHERE contentstreamidentifier = :contentStreamIdentifier
                            AND affectednodeaggregateidentifier = :parentNodeAggregateIdentifier
                            AND dimensionspacepointhash IN (:affectedDimensionSpacePointHashes)
                    ) AS joinedrestrictingancestors
            )',
            [
                'contentStreamIdentifier' => (string)$contentStreamIdentifier,
                'parentNodeAggregateIdentifier' => (string)$parentNodeAggregateIdentifier,
                'entryNodeAggregateIdentifier' => (string)$entryNodeAggregateIdentifier,
                'dimensionSpacePointHashes' => $affectedDimensionSpacePoints->getPointHashes(),
                'affectedDimensionSpacePointHashes' => $affectedDimensionSpacePoints->getPointHashes()
            ],
            [
                'dimensionSpacePointHashes' => Connection::PARAM_STR_ARRAY,
                'affectedDimensionSpacePointHashes' => Connection::PARAM_STR_ARRAY
            ]
        );
    }

    /**
     * @param NodeAggregateWasEnabled $event
     * @throws \Throwable
     */
    public function whenNodeAggregateWasEnabled(NodeAggregateWasEnabled $event)
    {
        $this->transactional(function () use ($event) {
            $this->removeOutgoingRestrictionRelationsOfNodeAggregateInDimensionSpacePoints($event->getContentStreamIdentifier(), $event->getNodeAggregateIdentifier(), $event->getAffectedDimensionSpacePoints());
        });
    }

    /**
     * @param NodeSpecializationVariantWasCreated $event
     * @throws \Exception
     * @throws \Throwable
     */
    public function whenNodeSpecializationVariantWasCreated(NodeSpecializationVariantWasCreated $event): void
    {
        $this->transactional(function () use ($event) {
            $sourceNode = $this->projectionContentGraph->findNodeInAggregate($event->getContentStreamIdentifier(), $event->getNodeAggregateIdentifier(), $event->getSourceOrigin());

            $specializedNode = $this->copyNodeToDimensionSpacePoint($sourceNode, $event->getSpecializationOrigin());

            foreach ($this->projectionContentGraph->findIngoingHierarchyRelationsForNode(
                $sourceNode->relationAnchorPoint,
                $event->getContentStreamIdentifier(),
                $event->getSpecializationCoverage()
            ) as $hierarchyRelation) {
                $hierarchyRelation->assignNewChildNode($specializedNode->relationAnchorPoint, $this->getDatabaseConnection());
            }
            foreach ($this->projectionContentGraph->findOutgoingHierarchyRelationsForNode(
                $sourceNode->relationAnchorPoint,
                $event->getContentStreamIdentifier(),
                $event->getSpecializationCoverage()
            ) as $hierarchyRelation) {
                $hierarchyRelation->assignNewParentNode($specializedNode->relationAnchorPoint, null, $this->getDatabaseConnection());
            }
        });
    }

    /**
     * @param NodeGeneralizationVariantWasCreated $event
     * @throws \Exception
     * @throws \Throwable
     */
    public function whenNodeGeneralizationVariantWasCreated(NodeGeneralizationVariantWasCreated $event): void
    {
        $this->transactional(function () use ($event) {
            $sourceNode = $this->projectionContentGraph->findNodeInAggregate($event->getContentStreamIdentifier(), $event->getNodeAggregateIdentifier(), $event->getSourceOrigin());
            $sourceParentNode = $this->projectionContentGraph->findParentNode(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getSourceOrigin()
            );
            $generalizedNode = $this->copyNodeToDimensionSpacePoint($sourceNode, $event->getGeneralizationOrigin());

            $unassignedIngoingDimensionSpacePoints = $event->getGeneralizationCoverage();
            foreach ($this->projectionContentGraph->findIngoingHierarchyRelationsForNodeAggregate(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getGeneralizationCoverage()
            ) as $existingIngoingHierarchyRelation) {
                $existingIngoingHierarchyRelation->assignNewChildNode($generalizedNode->relationAnchorPoint, $this->getDatabaseConnection());
                $unassignedIngoingDimensionSpacePoints = $unassignedIngoingDimensionSpacePoints->getDifference(new DimensionSpacePointSet([$existingIngoingHierarchyRelation->dimensionSpacePoint]));
            }

            foreach ($this->projectionContentGraph->findOutgoingHierarchyRelationsForNodeAggregate(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getGeneralizationCoverage()
            ) as $existingOutgoingHierarchyRelation) {
                $existingOutgoingHierarchyRelation->assignNewParentNode($generalizedNode->relationAnchorPoint, null, $this->getDatabaseConnection());
            }

            if (count($unassignedIngoingDimensionSpacePoints) > 0) {
                $ingoingSourceHierarchyRelation = $this->projectionContentGraph->findIngoingHierarchyRelationsForNode(
                        $sourceNode->relationAnchorPoint,
                        $event->getContentStreamIdentifier(),
                        new DimensionSpacePointSet([$event->getSourceOrigin()])
                    )[$event->getSourceOrigin()->getHash()] ?? null;
                // the null case is caught by the NodeAggregate or its command handler
                foreach ($unassignedIngoingDimensionSpacePoints as $unassignedDimensionSpacePoint) {
                    // The parent node aggregate might be varied as well, so we need to find a parent node for each covered dimension space point
                    $generalizationParentNode = $this->projectionContentGraph->findNodeInAggregate(
                        $event->getContentStreamIdentifier(),
                        $sourceParentNode->nodeAggregateIdentifier,
                        $unassignedDimensionSpacePoint
                    );

                    $this->copyHierarchyRelationToDimensionSpacePoint(
                        $ingoingSourceHierarchyRelation,
                        $event->getContentStreamIdentifier(),
                        $unassignedDimensionSpacePoint,
                        $generalizationParentNode->relationAnchorPoint,
                        $generalizedNode->relationAnchorPoint
                    );
                }
            }
        });
    }

    /**
     * @param Event\NodePeerVariantWasCreated $event
     * @throws \Throwable
     */
    public function whenNodePeerVariantWasCreated(Event\NodePeerVariantWasCreated $event)
    {
        $this->transactional(function () use ($event) {
            $sourceNode = $this->projectionContentGraph->findNodeInAggregate($event->getContentStreamIdentifier(), $event->getNodeAggregateIdentifier(), $event->getSourceOrigin());
            $sourceParentNode = $this->projectionContentGraph->findParentNode(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getSourceOrigin()
            );
            $peerNode = $this->copyNodeToDimensionSpacePoint($sourceNode, $event->getPeerOrigin());

            $unassignedIngoingDimensionSpacePoints = $event->getPeerCoverage();
            foreach ($this->projectionContentGraph->findIngoingHierarchyRelationsForNodeAggregate(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getPeerCoverage()
            ) as $existingIngoingHierarchyRelation) {
                $existingIngoingHierarchyRelation->assignNewChildNode($peerNode->relationAnchorPoint, $this->getDatabaseConnection());
                $unassignedIngoingDimensionSpacePoints = $unassignedIngoingDimensionSpacePoints->getDifference(new DimensionSpacePointSet([$existingIngoingHierarchyRelation->dimensionSpacePoint]));
            }

            foreach ($this->projectionContentGraph->findOutgoingHierarchyRelationsForNodeAggregate(
                $event->getContentStreamIdentifier(),
                $event->getNodeAggregateIdentifier(),
                $event->getPeerCoverage()
            ) as $existingOutgoingHierarchyRelation) {
                $existingOutgoingHierarchyRelation->assignNewParentNode($peerNode->relationAnchorPoint, null, $this->getDatabaseConnection());
            }

            foreach ($unassignedIngoingDimensionSpacePoints as $coveredDimensionSpacePoint) {
                // The parent node aggregate might be varied as well, so we need to find a parent node for each covered dimension space point
                $peerParentNode = $this->projectionContentGraph->findNodeInAggregate(
                    $event->getContentStreamIdentifier(),
                    $sourceParentNode->nodeAggregateIdentifier,
                    $coveredDimensionSpacePoint
                );

                $this->connectHierarchy(
                    $event->getContentStreamIdentifier(),
                    $peerParentNode->relationAnchorPoint,
                    $peerNode->relationAnchorPoint,
                    new DimensionSpacePointSet([$coveredDimensionSpacePoint]),
                    null, // @todo fetch appropriate sibling
                    $sourceNode->nodeName
                );
            }
        });
    }

    /**
     * @param HierarchyRelation $sourceHierarchyRelation
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @param NodeRelationAnchorPoint|null $newParent
     * @param NodeRelationAnchorPoint|null $newChild
     * @return HierarchyRelation
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function copyHierarchyRelationToDimensionSpacePoint(
        HierarchyRelation $sourceHierarchyRelation,
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint,
        ?NodeRelationAnchorPoint $newParent = null,
        ?NodeRelationAnchorPoint $newChild = null
    ): HierarchyRelation
    {
        $copy = new HierarchyRelation(
            $newParent ?: $sourceHierarchyRelation->parentNodeAnchor,
            $newChild ?: $sourceHierarchyRelation->childNodeAnchor,
            $sourceHierarchyRelation->name,
            $contentStreamIdentifier,
            $dimensionSpacePoint,
            $dimensionSpacePoint->getHash(),
            $this->getRelationPosition(
                $newParent ?: $sourceHierarchyRelation->parentNodeAnchor,
                $newChild ?: $sourceHierarchyRelation->childNodeAnchor,
                null, // todo: find proper sibling
                $contentStreamIdentifier,
                $dimensionSpacePoint
            )
        );
        $copy->addToDatabase($this->getDatabaseConnection());

        return $copy;
    }

    /**
     * @param NodeRecord $sourceNode
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @return NodeRecord
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function copyNodeToDimensionSpacePoint(NodeRecord $sourceNode, DimensionSpacePoint $dimensionSpacePoint): NodeRecord
    {
        $copyRelationAnchorPoint = NodeRelationAnchorPoint::create();
        $copy = new NodeRecord(
            $copyRelationAnchorPoint,
            $sourceNode->nodeAggregateIdentifier,
            $dimensionSpacePoint->jsonSerialize(),
            $dimensionSpacePoint->getHash(),
            $sourceNode->properties,
            $sourceNode->nodeTypeName,
            $sourceNode->classification,
            $sourceNode->nodeName
        );
        $copy->addToDatabase($this->getDatabaseConnection());

        return $copy;
    }

    /**
     * @param $event
     * @param callable $operations
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    protected function updateNodeWithCopyOnWrite($event, callable $operations)
    {
        switch (get_class($event)) {
            case NodeReferencesWereSet::class:
                /** @var NodeReferencesWereSet $event */
                $anchorPointForNode = $this->projectionContentGraph->getAnchorPointForNodeAndOriginDimensionSpacePointAndContentStream(
                    $event->getSourceNodeAggregateIdentifier(),
                    $event->getSourceOriginDimensionSpacePoint(),
                    $event->getContentStreamIdentifier()
                );
                break;
            default:
                if (method_exists($event, 'getNodeAggregateIdentifier')) {
                    $anchorPointForNode = $this->projectionContentGraph->getAnchorPointForNodeAndOriginDimensionSpacePointAndContentStream(
                        $event->getNodeAggregateIdentifier(),
                        $event->getOriginDimensionSpacePoint(),
                        $event->getContentStreamIdentifier()
                    );
                }
        }

        $contentStreamIdentifiers = $this->projectionContentGraph->getAllContentStreamIdentifiersAnchorPointIsContainedIn($anchorPointForNode);
        if (count($contentStreamIdentifiers) > 1) {
            // Copy on Write needed!
            // Copy on Write is a purely "Content Stream" related concept; thus we do not care about different DimensionSpacePoints here (but we copy all edges)

            // 1) fetch node, adjust properties, assign new Relation Anchor Point
            $copiedNode = $this->projectionContentGraph->getNodeByAnchorPoint($anchorPointForNode);
            $copiedNode->relationAnchorPoint = NodeRelationAnchorPoint::create();
            $result = $operations($copiedNode);
            $copiedNode->addToDatabase($this->getDatabaseConnection());

            // 2) reconnect all edges belonging to this content stream to the new "copied node". IMPORTANT: We need to reconnect
            // BOTH the incoming and outgoing edges.
            $this->getDatabaseConnection()->executeUpdate('
                UPDATE neos_contentgraph_hierarchyrelation h
                    SET
                        -- if our (copied) node is the child, we update h.childNodeAnchor
                        h.childnodeanchor = IF(h.childnodeanchor = :originalNodeAnchor, :newNodeAnchor, h.childnodeanchor),

                        -- if our (copied) node is the parent, we update h.parentNodeAnchor
                        h.parentnodeanchor = IF(h.parentnodeanchor = :originalNodeAnchor, :newNodeAnchor, h.parentnodeanchor)
                    WHERE
                      :originalNodeAnchor IN (h.childnodeanchor, h.parentnodeanchor)
                      AND h.contentstreamidentifier = :contentStreamIdentifier',
                [
                    'newNodeAnchor' => (string)$copiedNode->relationAnchorPoint,
                    'originalNodeAnchor' => (string)$anchorPointForNode,
                    'contentStreamIdentifier' => (string)$event->getContentStreamIdentifier()
                ]
            );
        } else {
            // No copy on write needed :)

            $node = $this->projectionContentGraph->getNodeByAnchorPoint($anchorPointForNode);
            if (!$node) {
                // TODO: ignore the ShowNode (if all other logic is correct)
                throw new \Exception("TODO NODE NOT FOUND");
            }

            $result = $operations($node);
            $node->updateToDatabase($this->getDatabaseConnection());
        }
        return $result;
    }
}
