<?php
declare(strict_types=1);

namespace Neos\ContentRepository\History\Domain\History\Projection;

/*
 * This file is part of the Neos.ContentRepository.History package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Neos\ContentRepository\History\Domain\History\AgentIdentifier;
use Neos\ContentRepository\History\Domain\History\HistoryEntryIdentifier;
use Neos\ContentRepository\History\Domain\History\HistoryEntryType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * The active record for reading and writing history entries from and to the database
 *
 * @Flow\Proxy(false)
 */
final class HistoryEntryRecord
{
    const TABLE_NAME = 'neos_neos_history_entry';

    /**
     * @var HistoryEntryIdentifier
     */
    public $identifier;

    /**
     * @var NodeAggregateIdentifier
     */
    public $nodeAggregateIdentifier;

    /**
     * @var HistoryEntryType
     */
    public $type;

    /**
     * @var AgentIdentifier
     */
    public $agentIdentifier;

    /**
     * @var \DateTimeImmutable
     */
    public $recordedAt;

    /**
     * @var array
     */
    public $payload;

    public function __construct(
        HistoryEntryIdentifier $identifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        HistoryEntryType $type,
        AgentIdentifier $agentIdentifier,
        \DateTimeImmutable $recordedAt,
        array $payload
    ) {
        $this->identifier = $identifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->type = $type;
        $this->agentIdentifier = $agentIdentifier;
        $this->recordedAt = $recordedAt;
        $this->payload = $payload;
    }

    /**
     * @param Connection $databaseConnection
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addToDatabase(Connection $databaseConnection): void
    {
        $databaseConnection->insert(
            self::TABLE_NAME,
            [
                'identifier' => (string)$this->identifier,
                'nodeaggregateidentifier' => (string)$this->nodeAggregateIdentifier,
                'type' => (string)$this->type,
                'agentidentifier' => (string)$this->agentIdentifier,
                'recordedat' => $this->recordedAt,
                'payload' => json_encode($this->payload)
            ],
            [
                'recordedat' => Types::DATETIME_IMMUTABLE,
            ]
        );
    }

    /**
     * @param Connection $databaseConnection
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateToDatabase(Connection $databaseConnection): void
    {
        $databaseConnection->update(
            self::TABLE_NAME,
            [
                'nodeaggregateidentifier' => (string)$this->nodeAggregateIdentifier,
                'type' => (string)$this->type,
                'agentidentifier' => (string)$this->agentIdentifier,
                'recordedat' => $this->recordedAt,
                'payload' => json_encode($this->payload)
            ],
            [
                'identifier' => (string)$this->identifier,
            ],
            [
                'recordedat' => Types::DATETIME_IMMUTABLE
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
        $databaseConnection->delete(
            self::TABLE_NAME,
            [
                'identifier' => (string)$this->identifier
            ]
        );
    }

    /**
     * @param array $databaseRow
     * @return static
     * @throws \Exception
     */
    public static function fromDatabaseRow(array $databaseRow): HistoryEntryRecord
    {
        return new static(
            HistoryEntryIdentifier::fromString($databaseRow['identifier']),
            NodeAggregateIdentifier::fromString($databaseRow['nodeaggregateidentifier']),
            HistoryEntryType::fromString($databaseRow['type']),
            AgentIdentifier::fromString($databaseRow['agentidentifier']),
            $databaseRow['recordedat'],
            json_decode($databaseRow['payload'], true)
        );
    }
}
