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

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodes;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Exception\NodeConfigurationException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeImplementationClassName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\PropertyConversionService;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\Node;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeAggregate;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\PropertyCollection;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Reflection\ReflectionService;

/**
 * Implementation detail of ContentGraph and ContentSubgraph
 *
 * @Flow\Scope("singleton")
 */
final class NodeFactory
{
    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var ReflectionService
     */
    protected $reflectionService;

    /**
     * @Flow\Inject(lazy=false)
     * @var PropertyConversionService
     */
    protected $propertyConversionService;

    /**
     * @param array $nodeRows
     * @param ContentSubgraphInterface $contentSubgraph
     * @return TraversableNodes
     * @throws NodeConfigurationException
     * @throws NodeTypeNotFoundException
     */
    public function mapNodeRowsToTraversableNodes(array $nodeRows, ContentSubgraphInterface $contentSubgraph): TraversableNodes
    {
        $result = [];
        foreach ($nodeRows as $nodeRecord) {
            $result[] = $this->mapNodeRowToTraversableNode($nodeRecord, $contentSubgraph);
        }

        return TraversableNodes::fromArray($result);
    }

    /**
     * @param array $nodeRow
     * @param ContentSubgraphInterface $contentSubgraph
     * @return TraversableNodeInterface
     * @throws NodeConfigurationException
     * @throws NodeTypeNotFoundException
     */
    public function mapNodeRowToTraversableNode(array $nodeRow, ContentSubgraphInterface $contentSubgraph): TraversableNodeInterface
    {
        $nodeType = $this->nodeTypeManager->getNodeType($nodeRow['nodetypename']);
        $className = NodeImplementationClassName::forNodeType($nodeType);
        $node = $this->mapNodeRowToNode($nodeRow);

        return new $className(
            $node,
            $contentSubgraph
        );
    }

    /**
     * @param array $nodeRow Node Row from projection (neos_contentgraph_node table)
     * @return NodeInterface
     * @throws NodeTypeNotFoundException
     */
    public function mapNodeRowToNode(array $nodeRow): NodeInterface
    {
        $nodeType = $this->nodeTypeManager->getNodeType($nodeRow['nodetypename']);

        $contentStreamIdentifier = ContentStreamIdentifier::fromString($nodeRow['contentstreamidentifier']);
        $originDimensionSpacePoint = OriginDimensionSpacePoint::fromJsonString($nodeRow['origindimensionspacepoint']);

        $properties = SerializedPropertyValues::fromArray(json_decode($nodeRow['properties'], true));

        $propertyCollection = new PropertyCollection($properties, $this->propertyConversionService);

        return new Node(
            $contentStreamIdentifier,
            NodeAggregateIdentifier::fromString($nodeRow['nodeaggregateidentifier']),
            $originDimensionSpacePoint,
            NodeTypeName::fromString($nodeRow['nodetypename']),
            $nodeType,
            isset($nodeRow['name']) ? NodeName::fromString($nodeRow['name']) : null,
            $propertyCollection,
            NodeAggregateClassification::fromString($nodeRow['classification'])
        );
    }

    /**
     * @param array $nodeRows
     * @return NodeAggregate|null
     * @throws NodeTypeNotFoundException
     */
    public function mapNodeRowsToNodeAggregate(array $nodeRows): ?NodeAggregate
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

        return new NodeAggregate(
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
     * @return array|NodeAggregate[]
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
            $nodeAggregates[$rawNodeAggregateIdentifier] = new NodeAggregate(
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
