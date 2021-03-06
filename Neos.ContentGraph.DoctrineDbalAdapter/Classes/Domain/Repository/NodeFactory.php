<?php
declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Domain\Repository;

/*
 * This file is part of the Neos.ContentGraph.DoctrineDbalAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection\Node;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Projection\Content as ContentProjection;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;
use Neos\Flow\Annotations as Flow;

/**
 * Implementation detail of ContentGraph and ContentSubgraph
 *
 * @Flow\Scope("singleton")
 */
final class NodeFactory
{
    private NodeTypeManager $nodeTypeManager;

    public function __construct(NodeTypeManager $nodeTypeManager)
    {
        $this->nodeTypeManager = $nodeTypeManager;
    }

    /**
     * @param array $nodeRow Node Row from projection (neos_contentgraph_node table)
     * @return Node
     * @throws NodeTypeNotFoundException
     */
    public function mapNodeRowToNode(array $nodeRow): Node
    {
        return new Node(
            ContentStreamIdentifier::fromString($nodeRow['contentstreamidentifier']),
            NodeAggregateIdentifier::fromString($nodeRow['nodeaggregateidentifier']),
            OriginDimensionSpacePoint::fromJsonString($nodeRow['origindimensionspacepoint']),
            NodeTypeName::fromString($nodeRow['nodetypename']),
            $this->nodeTypeManager->getNodeType($nodeRow['nodetypename']),
            isset($nodeRow['name']) ? NodeName::fromString($nodeRow['name']) : null,
            SerializedPropertyValues::fromArray(json_decode($nodeRow['properties'], true)),
            NodeAggregateClassification::fromString($nodeRow['classification'])
        );
    }

    /**
     * @param array $nodeRows
     * @return ContentProjection\NodeAggregate|null
     * @throws NodeTypeNotFoundException
     */
    public function mapNodeRowsToNodeAggregate(array $nodeRows): ?ContentProjection\NodeAggregate
    {
        if (empty($nodeRows)) {
            return null;
        }

        $rawNodeAggregateIdentifier = '';
        $rawNodeTypeName = '';
        $rawNodeName = '';
        $rawNodeAggregateClassification = '';
        $occupiedDimensionSpacePoints = [];
        $nodesByOccupiedDimensionSpacePoints = [];
        $coveredDimensionSpacePoints = [];
        $nodesByCoveredDimensionSpacePoints = [];
        $coverageByOccupants = [];
        $occupationByCovering = [];
        $disabledDimensionSpacePoints = [];

        foreach ($nodeRows as $nodeRow) {
            // A node can occupy exactly one DSP and cover multiple ones...
            $occupiedDimensionSpacePoint = OriginDimensionSpacePoint::fromJsonString($nodeRow['origindimensionspacepoint']);
            if (!isset($nodesByOccupiedDimensionSpacePoints[$occupiedDimensionSpacePoint->getHash()])) {
                // ... so we handle occupation exactly once ...
                $nodesByOccupiedDimensionSpacePoints[$occupiedDimensionSpacePoint->getHash()] = $this->mapNodeRowToNode($nodeRow);
                $occupiedDimensionSpacePoints[] = $occupiedDimensionSpacePoint;
                $rawNodeAggregateIdentifier = $rawNodeAggregateIdentifier ?: $nodeRow['nodeaggregateidentifier'];
                $rawNodeTypeName = $rawNodeTypeName ?: $nodeRow['nodetypename'];
                $rawNodeName = $rawNodeName ?: $nodeRow['name'];
                $rawNodeAggregateClassification = $rawNodeAggregateClassification ?: $nodeRow['classification'];
            }
            // ... and coverage always ...
            $coveredDimensionSpacePoint = DimensionSpacePoint::fromJsonString($nodeRow['covereddimensionspacepoint']);
            $coveredDimensionSpacePoints[$coveredDimensionSpacePoint->getHash()] = $coveredDimensionSpacePoint;

            $coverageByOccupants[$occupiedDimensionSpacePoint->getHash()][] = $coveredDimensionSpacePoint;
            $occupationByCovering[$coveredDimensionSpacePoint->getHash()] = $occupiedDimensionSpacePoint;
            $nodesByCoveredDimensionSpacePoints[$coveredDimensionSpacePoint->getHash()] = $nodesByOccupiedDimensionSpacePoints[$occupiedDimensionSpacePoint->getHash()];
            // ... as we do for disabling
            if (isset($nodeRow['disableddimensionspacepointhash'])) {
                $disabledDimensionSpacePoints[$coveredDimensionSpacePoint->getHash()] = $coveredDimensionSpacePoint;
            }
        }

        foreach ($coverageByOccupants as &$coverage) {
            $coverage = new DimensionSpacePointSet($coverage);
        }

        return new ContentProjection\NodeAggregate(
            current($nodesByOccupiedDimensionSpacePoints)->getContentStreamIdentifier(), // this line is safe because a nodeAggregate only exists if it at least contains one node.
            NodeAggregateIdentifier::fromString($rawNodeAggregateIdentifier),
            NodeAggregateClassification::fromString($rawNodeAggregateClassification),
            NodeTypeName::fromString($rawNodeTypeName),
            $rawNodeName ? NodeName::fromString($rawNodeName) : null,
            new OriginDimensionSpacePointSet($occupiedDimensionSpacePoints),
            $nodesByOccupiedDimensionSpacePoints,
            $coverageByOccupants,
            new DimensionSpacePointSet($coveredDimensionSpacePoints),
            $nodesByCoveredDimensionSpacePoints,
            $occupationByCovering,
            new DimensionSpacePointSet($disabledDimensionSpacePoints)
        );
    }

    /**
     * @param array $nodeRows
     * @return array|ContentProjection\NodeAggregate[]
     * @throws NodeTypeNotFoundException
     */
    public function mapNodeRowsToNodeAggregates(array $nodeRows): array
    {
        $nodeAggregates = [];
        $nodeTypeNames = [];
        $nodeNames = [];
        $occupiedDimensionSpacePointsByNodeAggregate = [];
        $nodesByOccupiedDimensionSpacePointsByNodeAggregate = [];
        $coveredDimensionSpacePointsByNodeAggregate = [];
        $nodesByCoveredDimensionSpacePointsByNodeAggregate = [];
        $classificationByNodeAggregate = [];
        $coverageByOccupantsByNodeAggregate = [];
        $occupationByCoveringByNodeAggregate = [];
        $disabledDimensionSpacePointsByNodeAggregate = [];

        foreach ($nodeRows as $nodeRow) {
            // A node can occupy exactly one DSP and cover multiple ones...
            $rawNodeAggregateIdentifier = $nodeRow['nodeaggregateidentifier'];
            $occupiedDimensionSpacePoint = OriginDimensionSpacePoint::fromJsonString($nodeRow['origindimensionspacepoint']);
            if (!isset($nodesByOccupiedDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier][$occupiedDimensionSpacePoint->getHash()])) {
                // ... so we handle occupation exactly once ...
                $nodesByOccupiedDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier][$occupiedDimensionSpacePoint->getHash()] = $this->mapNodeRowToNode($nodeRow);
                $occupiedDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier][] = $occupiedDimensionSpacePoint;
                $nodeTypeNames[$rawNodeAggregateIdentifier] = $nodeTypeNames[$rawNodeAggregateIdentifier] ?? NodeTypeName::fromString($nodeRow['nodetypename']);
                $nodeNames[$rawNodeAggregateIdentifier] = $nodeNames[$rawNodeAggregateIdentifier] ?? ($nodeRow['name'] ? NodeName::fromString($nodeRow['name']) : null);
                $classificationByNodeAggregate[$rawNodeAggregateIdentifier] = $classificationByNodeAggregate[$rawNodeAggregateIdentifier] ?? NodeAggregateClassification::fromString($nodeRow['classification']);
            }
            // ... and coverage always ...
            $coveredDimensionSpacePoint = DimensionSpacePoint::fromJsonString($nodeRow['covereddimensionspacepoint']);
            $coverageByOccupantsByNodeAggregate[$rawNodeAggregateIdentifier][$occupiedDimensionSpacePoint->getHash()][] = $coveredDimensionSpacePoint;
            $occupationByCoveringByNodeAggregate[$rawNodeAggregateIdentifier][$coveredDimensionSpacePoint->getHash()] = $occupiedDimensionSpacePoint;

            $coveredDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier][] = $coveredDimensionSpacePoint;
            $nodesByCoveredDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier][$coveredDimensionSpacePoint->getHash()]
                = $nodesByOccupiedDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier][$occupiedDimensionSpacePoint->getHash()];

            // ... as we do for disabling
            if (isset($nodeRow['disableddimensionspacepointhash'])) {
                $disabledDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier][$coveredDimensionSpacePoint->getHash()] = $coveredDimensionSpacePoint;
            }
        }


        foreach ($coverageByOccupantsByNodeAggregate as &$coverageByOccupants) {
            foreach ($coverageByOccupants as &$coverage) {
                $coverage = new DimensionSpacePointSet($coverage);
            }
        }

        foreach ($nodesByOccupiedDimensionSpacePointsByNodeAggregate as $rawNodeAggregateIdentifier => $nodes) {
            $nodeAggregates[$rawNodeAggregateIdentifier] = new ContentProjection\NodeAggregate(
                current($nodes)->getContentStreamIdentifier(), // this line is safe because a nodeAggregate only exists if it at least contains one node.
                NodeAggregateIdentifier::fromString($rawNodeAggregateIdentifier),
                $classificationByNodeAggregate[$rawNodeAggregateIdentifier],
                $nodeTypeNames[$rawNodeAggregateIdentifier],
                $nodeNames[$rawNodeAggregateIdentifier],
                new OriginDimensionSpacePointSet($occupiedDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier]),
                $nodesByOccupiedDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier],
                $coverageByOccupantsByNodeAggregate[$rawNodeAggregateIdentifier],
                new DimensionSpacePointSet($coveredDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier]),
                $nodesByCoveredDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier],
                $occupationByCoveringByNodeAggregate[$rawNodeAggregateIdentifier],
                new DimensionSpacePointSet($disabledDimensionSpacePointsByNodeAggregate[$rawNodeAggregateIdentifier] ?? [])
            );
        }

        return $nodeAggregates;
    }
}
