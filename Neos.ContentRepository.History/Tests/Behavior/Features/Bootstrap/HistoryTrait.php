<?php
declare(strict_types=1);

/*
 * This file is part of the Neos.ContentRepository.History package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\History\Domain\History\AgentIdentifier;
use Neos\ContentRepository\History\Domain\History\CreationHistoryEntry;
use Neos\ContentRepository\History\Domain\History\History;
use Neos\ContentRepository\History\Domain\History\HistoryEntryInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\Projection\Workspace\WorkspaceFinder;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventStore\EventStore;
use PHPUnit\Framework\Assert;

/**
 * The history trait for Behat feature contexts
 */
trait HistoryTrait
{
    /**
     * @var EventTypeResolver
     */
    private $eventTypeResolver;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var History
     */
    private $history;

    /**
     * @var array|HistoryEntryInterface[]
     */
    private $currentNodeAggregateHistory;

    /**
     * @var WorkspaceFinder
     */
    private $workspaceFinder;

    /**
     * @var NodeTypeConstraintFactory
     */
    private $nodeTypeConstraintFactory;

    /**
     * @var array
     */
    private $currentEventStreamAsArray = null;

    /**
     * @var \Exception
     */
    private $lastCommandException = null;

    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var DimensionSpacePoint
     */
    private $dimensionSpacePoint;

    /**
     * @var NodeInterface
     */
    protected $currentNode;

    /**
     * @var CommandResult
     */
    protected $lastCommandOrEventResult;


    /**
     * @When /^the history projection is fully up to date$/
     */
    public function theHistoryProjectionIsFullyUpToDate()
    {
        if ($this->lastCommandOrEventResult === null) {
            throw new \RuntimeException('lastCommandOrEventResult not filled; so I cannot block!');
        }
        $this->lastCommandOrEventResult->blockUntilProjectionsAreUpToDate();
        $this->lastCommandOrEventResult = null;
    }

    /**
     * @Then /^I expect the history for node aggregate "([^"]*)" to consist of exactly (\d+) entries$/
     * @param string $nodeAggregateIdentifier
     * @param int $expectedNumberOfEntries
     * @throws \Doctrine\DBAL\DBALException
     */
    public function iExpectTheHistoryForNodeAggregateToContainExactlyEntries(string $nodeAggregateIdentifier, int $expectedNumberOfEntries)
    {
        $this->currentNodeAggregateHistory = $this->history->findForNodeAggregate(NodeAggregateIdentifier::fromString($nodeAggregateIdentifier));
        $actualNumberOfEntries = count($this->currentNodeAggregateHistory);

        Assert::assertSame($expectedNumberOfEntries, $actualNumberOfEntries, 'History for node aggregate . ' . $nodeAggregateIdentifier . 'consists of ' . $actualNumberOfEntries . ' entries, expected were ' . $expectedNumberOfEntries . '.');
    }

    /**
     * @Then /^I expect history entry number (\d+) to be:$/
     * @param TableNode $expectedEntry
     */
    public function iExpectHistoryEntryNumberToBe(int $expectedIndex, TableNode $expectedEntry)
    {
        $expectedClassName = 'Neos\\ContentRepository\\History\\Domain\\History\\' . $expectedEntry['type'];
        Assert::assertInstanceOf($expectedClassName, get_class($this->currentNodeAggregateHistory[$expectedIndex] ?? new \stdClass()));
        $historyEntry = $this->currentNodeAggregateHistory[$expectedIndex];
        Assert::assertTrue(NodeAggregateIdentifier::fromString($expectedEntry['nodeAggregateIdentifier'])->equals($historyEntry->getNodeAggregateIdentifier()));
        Assert::assertTrue(AgentIdentifier::fromString($expectedEntry['agentIdentifier'])->equals($historyEntry->getAgentIdentifier()));
        if (isset($expectedEntry['nodeTypeName'])) {
            /** @var CreationHistoryEntry $historyEntry */
            Assert::assertTrue(NodeTypeName::fromString($expectedEntry['nodeTypeName'])->equals($historyEntry->getNodeTypeName()));
        }
        if (isset($expectedEntry['originDimensionSpacePoint'])) {
            /** @var CreationHistoryEntry $historyEntry */
            Assert::assertTrue(DimensionSpacePoint::fromArray(json_decode($expectedEntry['originDimensionSpacePoint'], true))->equals($historyEntry->getOriginDimensionSpacePoint()));
        }
        if (isset($expectedEntry['initialPropertyValues'])) {
            /** @var CreationHistoryEntry $historyEntry */
            $expectedInitialPropertyValues = json_decode($expectedEntry['initialPropertyValues'], true);
            $properties = $historyEntry->getInitialPropertyValues();
            foreach ($expectedInitialPropertyValues as $propertyName => $expectedPropertyValue) {
                Assert::assertArrayHasKey($propertyName, $properties, 'Property "' . $propertyName . '" not found');
                $actualPropertyValue = $properties[$propertyName];
                Assert::assertEquals($expectedPropertyValue, $actualPropertyValue, 'Node property ' . $propertyName . ' does not match. Expected: ' . $expectedPropertyValue . '; Actual: ' . $actualPropertyValue);
            }
        }
    }
}
