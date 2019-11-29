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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\History\Infrastructure\DbalClient;
use Neos\Flow\Annotations as Flow;

/**
 * The history domain repository
 */
final class History
{
    const TABLE_NAME = 'neos_contentrepository_history_entry';

    /**
     * @Flow\Inject
     * @var DbalClient
     */
    protected $databaseClient;

    /**
     * @var HistoryEntryFactory
     */
    protected $historyEntryFactory;

    public function __construct()
    {
        $this->historyEntryFactory = new HistoryEntryFactory();
    }

    /**
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param HistoryEntryTypes|null $historyEntryTypes
     * @param \DateTimeImmutable|null $from
     * @param \DateTimeImmutable|null $to
     * @param int|null $limit
     * @return array|HistoryEntryInterface[]
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findForNodeAggregate(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        ?HistoryEntryTypes $historyEntryTypes = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        ?int $limit = null
    ): array {
        $historyEntries = [];

        $query = 'SELECT * FROM ' . self::TABLE_NAME
            . ' WHERE nodeaggregateidentifier = :nodeAggregateIdentifier';
        if ($historyEntryTypes) {
            $query .= ' AND type IN (:historyEntryTypes)';
        }
        if ($from) {
            $query .= ' AND recordedat >= :from';
        }
        if ($to) {
            $query .= ' AND recordedat <= :to';
        }

        $query .= ' ORDER BY recordedat DESC';

        if ($limit) {
            $query .= ' LIMIT ' . $limit;
        }

        $historyRecords = $this->getDatabaseConnection()->executeQuery(
            $query,
            [
                'nodeAggregateIdentifier' => (string) $nodeAggregateIdentifier,
                'historyEntryTypes' => $historyEntryTypes->toPlainArray(),
                'from' => $from,
                'to' => $to
            ],
            [
                'historyEntryTypes' => Connection::PARAM_STR_ARRAY,
                'from' => Type::DATETIME_IMMUTABLE,
                'to' => Type::DATETIME_IMMUTABLE,
            ]
        )->fetchAll();

        foreach ($historyRecords as $historyRecord) {
            $historyEntries[] = $this->historyEntryFactory->mapHistoryEntryRowToHistoryEntry($historyRecord);
        }

        return $historyEntries;
    }

    private function getDatabaseConnection(): Connection
    {
        return $this->databaseClient->getConnection();
    }
}
