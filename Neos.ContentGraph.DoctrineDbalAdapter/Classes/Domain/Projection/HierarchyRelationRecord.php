<?php
declare(strict_types=1);

namespace Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection;

/*
 * This file is part of the Neos.ContentGraph.DoctrineDbalAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Annotations as Flow;

/**
 * The active record for reading and writing hierarchy relations from and to the database
 *
 * @Flow\Proxy(false)
 */
class HierarchyRelationRecord
{
    public NodeRelationAnchorPoint $parentNodeAnchor;

    public NodeRelationAnchorPoint $childNodeAnchor;

    public ?NodeName $name;

    public ContentStreamIdentifier $contentStreamIdentifier;

    public DimensionSpacePoint $dimensionSpacePoint;

    public string $dimensionSpacePointHash;

    public int $position;

    public function __construct(
        NodeRelationAnchorPoint $parentNodeAnchor,
        NodeRelationAnchorPoint $childNodeAnchor,
        ?NodeName $name,
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint,
        string $dimensionSpacePointHash,
        int $position
    ) {
        $this->parentNodeAnchor = $parentNodeAnchor;
        $this->childNodeAnchor = $childNodeAnchor;
        $this->name = $name;
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->dimensionSpacePoint = $dimensionSpacePoint;
        $this->dimensionSpacePointHash = $dimensionSpacePointHash;
        $this->position = $position;
    }

    public static function fromDatabaseRow(array $databaseRow): self
    {
        return new self(
            NodeRelationAnchorPoint::fromString($databaseRow['parentnodeanchor']),
            NodeRelationAnchorPoint::fromString($databaseRow['childnodeanchor']),
            $databaseRow['name'] ? NodeName::fromString($databaseRow['name']) : null,
            ContentStreamIdentifier::fromString($databaseRow['contentstreamidentifier']),
            DimensionSpacePoint::fromArray(json_decode($databaseRow['dimensionspacepoint'], true)),
            $databaseRow['dimensionspacepointhash'],
            (int)$databaseRow['position']
        );
    }

    public function addToDatabase(Connection $databaseConnection): void
    {
        $databaseConnection->insert('neos_contentgraph_hierarchyrelation', [
            'parentnodeanchor' => $this->parentNodeAnchor,
            'childnodeanchor' => $this->childNodeAnchor,
            'name' => $this->name,
            'contentstreamidentifier' => $this->contentStreamIdentifier,
            'dimensionspacepoint' => json_encode($this->dimensionSpacePoint),
            'dimensionspacepointhash' => $this->dimensionSpacePointHash,
            'position' => $this->position
        ]);
    }

    public function removeFromDatabase(Connection $databaseConnection): void
    {
        $databaseConnection->delete('neos_contentgraph_hierarchyrelation', $this->getDatabaseIdentifier());
    }

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
