<?php

/*
 * This file is part of the Neos.ContentGraph.PostgreSQLAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class HypergraphSiblingQuery implements HypergraphQueryInterface
{
    use CommonGraphQueryOperations;

    public static function create(
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        HypergraphSiblingQueryMode $queryMode
    ): self {
        $query = /** @lang PostgreSQL */
            'SELECT sn.*, sh.contentstreamidentifier, sh.dimensionspacepoint, ordinality, childnodeanchor
    FROM neos_contentgraph_node n
        JOIN neos_contentgraph_hierarchyhyperrelation sh ON n.relationanchorpoint = ANY(sh.childnodeanchors),
            unnest(sh.childnodeanchors) WITH ORDINALITY childnodeanchor
        JOIN neos_contentgraph_node sn ON childnodeanchor = sn.relationanchorpoint
    WHERE sh.contentstreamidentifier = :contentStreamIdentifier
        AND sh.dimensionspacepointhash = :dimensionSpacePointHash
        AND n.nodeaggregateidentifier = :nodeAggregateIdentifier
        AND childnodeanchor != n.relationanchorpoint'
                . $queryMode->renderCondition();

        $parameters = [
            'contentStreamIdentifier' => (string)$contentStreamIdentifier,
            'dimensionSpacePointHash' => $dimensionSpacePoint->hash,
            'nodeAggregateIdentifier' => (string)$nodeAggregateIdentifier
        ];

        return new self($query, $parameters);
    }

    public function withRestriction(VisibilityConstraints $visibilityConstraints): self
    {
        $query = $this->query . QueryUtility::getRestrictionClause($visibilityConstraints, 's');

        return new self($query, $this->parameters, $this->types);
    }

    public function withOrdinalityOrdering(bool $reverse): self
    {
        $query = $this->query . '
    ORDER BY ordinality ' . ($reverse ? 'DESC' : 'ASC');

        return new self($query, $this->parameters, $this->types);
    }
}
