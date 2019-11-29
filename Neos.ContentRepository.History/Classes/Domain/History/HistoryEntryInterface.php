<?php

namespace Neos\ContentRepository\History\Domain\History;


use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;

/**
 * The common interface for history entry read models
 */
interface HistoryEntryInterface
{
    public function getIdentifier(): HistoryEntryIdentifier;

    public function getNodeAggregateLabel(): string;

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier;

    public function getAgentIdentifier(): AgentIdentifier;

    public function getRecordedAt(): \DateTimeImmutable;
}
