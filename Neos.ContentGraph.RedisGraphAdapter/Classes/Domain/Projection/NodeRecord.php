<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Domain\Projection;

/*
 * This file is part of the Neos.ContentGraph package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Doctrine\DBAL\Connection;
use Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient\Graph\Graph;
use Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient\Graph\GraphNode;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;

/**
 * The active record for reading and writing nodes from and to the database
 */
class NodeRecord
{

    /**
     * @var NodeAggregateIdentifier
     */
    public $nodeAggregateIdentifier;

    /**
     * @var array
     */
    public $originDimensionSpacePoint;

    /**
     * @var string
     */
    public $originDimensionSpacePointHash;

    /**
     * @var array
     */
    public $properties;

    /**
     * @var NodeTypeName
     */
    public $nodeTypeName;

    /**
     * Transient node name to store a node name after fetching a node with hierarchy (not always available)
     *
     * @var NodeName
     */
    public $nodeName;

    /**
     * @var NodeAggregateClassification
     */
    public $classification;
    /**
     * @var GraphNode
     */
    private $graphNode;

    public function __construct(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        ?array $originDimensionSpacePoint,
        ?string $originDimensionSpacePointHash,
        ?array $properties,
        NodeTypeName $nodeTypeName,
        NodeAggregateClassification $classification,
        NodeName $nodeName = null
    ) {
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->originDimensionSpacePoint = $originDimensionSpacePoint;
        $this->originDimensionSpacePointHash = $originDimensionSpacePointHash;
        $this->properties = $properties;
        $this->nodeTypeName = $nodeTypeName;
        $this->classification = $classification;
        $this->nodeName = $nodeName;

        $this->graphNode = GraphNode::create('node', [
            'nodeaggregateidentifier' => (string) $this->nodeAggregateIdentifier,
            'origindimensionspacepoint' => json_encode($this->originDimensionSpacePoint),
            'origindimensionspacepointhash' => (string) $this->originDimensionSpacePointHash,
            'properties' => json_encode($this->properties),
            'nodetypename' => (string) $this->nodeTypeName,
            'classification' => (string) $this->classification
        ])
    }

    public function toGraphNode(): GraphNode {
        return $this->graphNode;
    }

    /**
     * @param Connection $databaseConnection
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateToDatabase(Connection $databaseConnection): void
    {
        $databaseConnection->update(
            'neos_contentgraph_node',
            [
                'nodeaggregateidentifier' => (string) $this->nodeAggregateIdentifier,
                'origindimensionspacepoint' => json_encode($this->originDimensionSpacePoint),
                'origindimensionspacepointhash' => (string) $this->originDimensionSpacePointHash,
                'properties' => json_encode($this->properties),
                'nodetypename' => (string) $this->nodeTypeName,
                'classification' => (string) $this->classification
            ],
            [
                'relationanchorpoint' => $this->relationAnchorPoint
            ]
        );
    }

    /**
     * @param Connection $databaseConnection
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function removeFromDatabase(Connection $databaseConnection): void
    {
        $databaseConnection->delete('neos_contentgraph_node', [
            'relationanchorpoint' => $this->relationAnchorPoint
        ]);
    }

    /**
     * @param array $databaseRow
     * @return static
     * @throws \Exception
     */
    public static function fromDatabaseRow(array $databaseRow): NodeRecord
    {
        return new static(
            NodeRelationAnchorPoint::fromString($databaseRow['relationanchorpoint']),
            NodeAggregateIdentifier::fromString($databaseRow['nodeaggregateidentifier']),
            json_decode($databaseRow['origindimensionspacepoint'], true),
            $databaseRow['origindimensionspacepointhash'],
            json_decode($databaseRow['properties'], true),
            NodeTypeName::fromString($databaseRow['nodetypename']),
            NodeAggregateClassification::fromString($databaseRow['classification']),
            isset($databaseRow['name']) ? NodeName::fromString($databaseRow['name']) : null
        );
    }
}
