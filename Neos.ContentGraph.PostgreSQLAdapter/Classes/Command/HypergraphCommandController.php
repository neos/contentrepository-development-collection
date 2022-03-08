<?php
declare(strict_types=1);

namespace Neos\ContentGraph\PostgreSQLAdapter\Command;

/*
 * This file is part of the Neos.ContentGraph.PostgreSQLAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\ContentHypergraph;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\ContentSubhypergraph;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query\CypherPattern;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\Flow\Cli\CommandController;

final class HypergraphCommandController extends CommandController
{
    private ContentHypergraph $hypergraph;

    public function __construct(ContentHypergraph $hypergraph)
    {
        $this->hypergraph = $hypergraph;
        parent::__construct();
    }

    public function findNodeAggregateCommand(): void
    {
        $nodeAggregate = $this->hypergraph->findNodeAggregateByIdentifier(
            ContentStreamIdentifier::fromString('cs-identifier'),
            NodeAggregateIdentifier::fromString('sir-david-nodenborough')
        );
    }

    public function cypherQueryCommand(string $query): void
    {
        $cypherPattern = CypherPattern::tryFromString($query);
        if (is_null($cypherPattern)) {
            throw new \InvalidArgumentException('could not read cypher pattern ' . $query);
        }
        /** @var ContentSubhypergraph $subgraph */
        $subgraph = $this->hypergraph->getSubgraphByIdentifier(
            ContentStreamIdentifier::fromString('cs-identifier'),
            DimensionSpacePoint::fromArray([]),
            VisibilityConstraints::withoutRestrictions()
        );

        foreach ($subgraph->findByCypherPattern($cypherPattern) as $node) {
            $this->outputLine('found node');
            /** @var NodeInterface $node */
            echo json_encode([
                'contentStreamIdentifier' => $node->getContentStreamIdentifier(),
                'originDimensionSpacePoint' => $node->getOriginDimensionSpacePoint(),
                'nodeAggregateIdentifier' => $node->getNodeAggregateIdentifier(),
                'classification' => $node->getClassification(),
                'nodeType' => $node->getNodeTypeName(),
                'properties' => $node->getProperties()
            ], JSON_PRETTY_PRINT);
        }
        $this->outputLine('');
    }
}
