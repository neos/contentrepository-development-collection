<?php
declare(strict_types=1);

namespace Neos\ContentRepository\History\Domain\History;

/*
 * This file is part of the Neos.ContentRepository.History package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\Model\ArrayPropertyCollection;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;

/**
 * The history entry factory
 */
final class HistoryEntryFactory
{
    public function mapHistoryEntryRowToHistoryEntry(array $historyEntryRow): HistoryEntryInterface
    {
        $payload = json_decode($historyEntryRow['payload'], true);
        switch ($historyEntryRow['type']) {
            case HistoryEntryType::TYPE_CREATED:
            default:
                return new CreationHistoryEntry(
                    HistoryEntryIdentifier::fromString($historyEntryRow['identifier']),
                    $payload['nodeAggregateLabel'],
                    NodeAggregateIdentifier::fromString($historyEntryRow['nodeaggregateidentifier']),
                    AgentIdentifier::fromString('agentidentifier'),
                    new \DateTimeImmutable($historyEntryRow['recordedat']['date'], new \DateTimeZone($historyEntryRow['recordedat']['timezone'])),
                    NodeTypeName::fromString($payload['nodeTypeName']),
                    DimensionSpacePoint::fromArray($payload['originDimensionSpacePoint']),
                    $payload['parentNodeAggregateLabel'],
                    NodeAggregateIdentifier::fromString($payload['parentNodeAggregateIdentifier']),
                    NodeName::fromString($payload['nodeName']),
                    new ArrayPropertyCollection($payload['initialPropertyValues'])
                );
        }
    }
}
