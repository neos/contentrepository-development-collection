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
use Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\CypherConversion;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;

/**
 * The active record for reading and writing hierarchy relations from and to the database
 */
class HierarchyRelation
{

    /**
     * @var NodeName
     */
    public $name;

    /**
     * @var ContentStreamIdentifier
     */
    public $contentStreamIdentifier;

    /**
     * @var DimensionSpacePoint
     */
    public $dimensionSpacePoint;

    /**
     * @var string
     */
    public $dimensionSpacePointHash;

    /**
     * @var int
     */
    public $position;

    /**
     * @param GraphNode $parentNodeAnchor
     * @param GraphNode $childNodeAnchor
     * @param NodeName $name
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @param string $dimensionSpacePointHash
     * @param int $position
     */
    public function __construct(
        ?NodeName $name,
        DimensionSpacePoint $dimensionSpacePoint,
        string $dimensionSpacePointHash,
        int $position
    ) {
        $this->name = $name;
        $this->dimensionSpacePoint = $dimensionSpacePoint;
        $this->dimensionSpacePointHash = $dimensionSpacePointHash;
        $this->position = $position;
    }

    public function toCypherProperties(): string {
        return CypherConversion::propertiesToCypher([
            'name' => (string) $this->name,
            'dimensionSpacePoint' => json_encode($this->dimensionSpacePoint),
            'dimensionSpacePointHash' => (string) $this->dimensionSpacePointHash,
            'position' => $this->position
        ]);
    }

    /**
     * @param Connection $databaseConnection
     */
    public function removeFromDatabase(Connection $databaseConnection): void
    {
        $databaseConnection->delete('neos_contentgraph_hierarchyrelation', $this->getDatabaseIdentifier());
    }

    /**
     * @param NodeRelationAnchorPoint $childAnchorPoint
     * @param Connection $databaseConnection
     */
    public function assignNewChildNode(NodeRelationAnchorPoint $childAnchorPoint, Connection $databaseConnection): void
    {
        $databaseConnection->update(
            'neos_contentgraph_hierarchyrelation',
            [
                'childnodeanchor' => $childAnchorPoint
            ],
            $this->getDatabaseIdentifier()
        );
    }

    /**
     * @param NodeRelationAnchorPoint $parentAnchorPoint
     * @param int|null $position
     * @param Connection $databaseConnection
     * @throws \Doctrine\DBAL\DBALException
     */
    public function assignNewParentNode(NodeRelationAnchorPoint $parentAnchorPoint, ?int $position, Connection $databaseConnection): void
    {
        $data = [
            'parentnodeanchor' => $parentAnchorPoint
        ];
        if (!is_null($position)) {
            $data['position'] = $position;
        }
        $databaseConnection->update(
            'neos_contentgraph_hierarchyrelation',
            $data,
            $this->getDatabaseIdentifier()
        );
    }
    /**
     * @param int $position
     * @param Connection $databaseConnection
     */
    public function assignNewPosition(int $position, Connection $databaseConnection): void
    {
        $databaseConnection->update(
            'neos_contentgraph_hierarchyrelation',
            [
                'position' => $position
            ],
            $this->getDatabaseIdentifier()
        );
    }

    /**
     * @return array
     */
    public function getDatabaseIdentifier(): array
    {
        return [
            'parentnodeanchor' => $this->parentNodeAnchor,
            'childnodeanchor' => $this->childNodeAnchor,
            'contentstreamidentifier' => $this->contentStreamIdentifier,
            'dimensionspacepointhash' => $this->dimensionSpacePointHash
        ];
    }


}
