<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\NodeImportFromLegacyCR\Service;

/*
 * This file is part of the Neos.ContentRepositoryMigration package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Persistence\ObjectManager as DoctrineObjectManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\ContentDimensionZookeeper;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\ValueObject\NodePath;
use Neos\ContentRepository\Domain\ValueObject\RootNodeIdentifiers;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Event\ContentStreamWasCreated;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\InterDimensionalVariationGraph;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Command\CreateRootNode;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Event\NodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Event\NodeWasAddedToAggregate;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Event\NodeReferencesWereSet;
use Neos\EventSourcedContentRepository\Domain\Context\Node\Event\RootNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\Node\NodeCommandHandler;
use Neos\EventSourcedContentRepository\Domain\Context\Workspace\Event\RootWorkspaceWasCreated;
use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeIdentifier;
use Neos\ContentRepository\Domain\ValueObject\NodeName;
use Neos\ContentRepository\Domain\ValueObject\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValue;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceDescription;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceTitle;
use Neos\EventSourcing\Event\Decorator\EventWithIdentifier;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventStoreManager;
use Neos\EventSourcing\EventStore\StreamName;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Doctrine\ORM\EntityManager as DoctrineEntityManager;
use Neos\Utility\TypeHandling;

/**
 * @Flow\Scope("singleton")
 */
class ContentRepositoryExportService
{

    /**
     * Doctrine's Entity Manager. Note that "ObjectManager" is the name of the related
     * interface ...
     *
     * @var DoctrineObjectManager
     */
    protected $entityManager;

    /**
     * @Flow\Inject
     * @var InterDimensionalVariationGraph
     */
    protected $interDimensionalFallbackGraph;

    /**
     * @var Connection
     */
    private $dbal;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    protected $contentStreamIdentifier;

    /**
     * @Flow\Inject
     * @var NodeCommandHandler
     */
    protected $nodeCommandHandler;

    /**
     * @Flow\Inject
     * @var EventStoreManager
     */
    protected $eventStoreManager;

    /**
     * @Flow\Inject
     * @var ContentDimensionZookeeper
     */
    protected $contentDimensionZookeeper;

    /**
     * @var NodeIdentifier
     */
    protected $sitesRootNodeIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifierForSitesNode;

    private $nodeIdentifiers;

    private $alreadyCreatedNodeAggregateIdentifiers;

    /**
     * @var CommandResult
     */
    private $commandResult;


    public function injectEntityManager(DoctrineObjectManager $entityManager)
    {
        if (!$entityManager instanceof DoctrineEntityManager) {
            throw new \RuntimeException('Invalid EntityManager configured');
        }
        $this->dbal = $entityManager->getConnection();
        $this->entityManager = $entityManager;
    }


    public function reset()
    {
        $this->dbal->executeQuery('
            SET foreign_key_checks = 0;
            
            TRUNCATE neos_eventsourcing_eventstore_events;
            TRUNCATE neos_eventsourcing_eventlistener_appliedeventslog;
            
            TRUNCATE neos_contentgraph_hierarchyrelation;
            TRUNCATE neos_contentgraph_node;
            TRUNCATE neos_contentgraph_referencerelation;
            TRUNCATE neos_contentgraph_restrictionedge;
            TRUNCATE neos_contentrepository_projection_change;
            TRUNCATE neos_contentrepository_projection_nodehiddenstate;
            TRUNCATE neos_contentrepository_projection_workspace_v1;
            TRUNCATE neos_neos_projection_domain_v1;
            TRUNCATE neos_neos_projection_site_v1;
            
            SET foreign_key_checks = 1;');
    }

    public function migrate()
    {
        $this->nodeIdentifiers = [];
        $this->alreadyCreatedNodeAggregateIdentifiers = [];

        $this->contentStreamIdentifier = ContentStreamIdentifier::create();
        $this->sitesRootNodeIdentifier = NodeIdentifier::create();
        $this->nodeAggregateIdentifierForSitesNode = NodeAggregateIdentifier::create();
        $this->commandResult = CommandResult::createEmpty();

        $streamName = $this->contentStreamName();
        $event = new ContentStreamWasCreated(
            $this->contentStreamIdentifier,
            UserIdentifier::forSystemUser()
        );
        $this->commitEvent($streamName, $event);

        $this->createRootWorkspace();
        $this->createRootNode();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('n')
            ->from(NodeData::class, 'n')
            ->where('n.workspace = :workspace')
            ->andWhere('n.movedTo IS NULL OR n.removed = :removed')
            ->andWhere('n.path NOT IN(\'/sites\', \'/\')')
            ->orderBy('n.parentPath', 'ASC')
            ->addOrderBy('n.index', 'ASC')
            ->setParameter('workspace', 'live')
            ->setParameter('removed', false, \PDO::PARAM_BOOL);

        $nodeDatas = $queryBuilder->getQuery()->getResult();
        $nodeDatasToExportAtNextIteration = [];
        foreach ($nodeDatas as $nodeData) {
            $this->exportNodeData($nodeData, null, $nodeDatasToExportAtNextIteration);
        }
        var_dump("NODE DATAS IN NEXT ITER: " . count($nodeDatasToExportAtNextIteration));


        $nodeDatas = $nodeDatasToExportAtNextIteration;
        $nodeDatasToExportAtNextIteration = [];
        // TODO: correct sorting with respect to iteration!!
        foreach ($nodeDatas as $nodeData) {
            $this->exportNodeData($nodeData, null, $nodeDatasToExportAtNextIteration);
        }
        var_dump("NODE DATAS IN NEXT ITER: " . count($nodeDatasToExportAtNextIteration));

        $this->commandResult->blockUntilProjectionsAreUpToDate();
    }

    protected function exportNodeData(NodeData $nodeData, DimensionSpacePoint $dimensionRestriction = null, &$nodeDatasToExportAtNextIteration)
    {
        $nodePath = NodePath::fromString($nodeData->getPath());

        $dimensionSpacePoint = DimensionSpacePoint::fromLegacyDimensionArray($nodeData->getDimensionValues());
        if ($dimensionRestriction !== null && $dimensionSpacePoint->getHash() !== $dimensionRestriction->getHash()) {
            // unwanted dimension; so let's skip it!
            return;
        }

        $parentNodeIdentifier = $this->findParentNodeIdentifier($nodeData->getParentPath(), $dimensionSpacePoint);
        if (!$parentNodeIdentifier) {
            // if parent node identifier not found, TRY LATER
            $nodeDatasToExportAtNextIteration[] = $nodeData;
            return;
        }


        $nodeAggregateIdentifier = NodeAggregateIdentifier::fromString($nodeData->getIdentifier());



        //$excludedSet = $this->findOtherExistingDimensionSpacePointsForNodeData($nodeData);
        $excludedSet = new DimensionSpacePointSet([]);
        $nodeIdentifier = NodeIdentifier::fromString($this->persistenceManager->getIdentifierByObject($nodeData));
        $this->exportNodeOrNodeAggregate(
            $nodeAggregateIdentifier,
            NodeTypeName::fromString($nodeData->getNodeType()->getName()),
            $dimensionSpacePoint,
            $excludedSet,
            $nodeIdentifier,
            $parentNodeIdentifier,
            NodeName::fromString($nodeData->getName()),
            $this->processPropertyValues($nodeData),
            $this->processPropertyReferences($nodeData),
            $nodePath // TODO: probably pass last path-part only?
        );
    }

    protected function exportNodeOrNodeAggregate(
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        NodeTypeName $nodeTypeName,
        DimensionSpacePoint $dimensionSpacePoint,
        DimensionSpacePointSet $excludedSet,
        NodeIdentifier $nodeIdentifier,
        NodeIdentifier $parentNodeIdentifier,
        NodeName $nodeName,
        array $propertyValues,
        array $propertyReferences,
        NodePath $nodePath
    ) {
        $visibleInDimensionSpacePoints = $this->interDimensionalFallbackGraph->getSpecializationSet($dimensionSpacePoint, true, $excludedSet);
        $this->recordNodeIdentifier($nodePath, $dimensionSpacePoint, $nodeIdentifier);
        $streamName = StreamName::fromString('NodeAggregate:' . $nodeIdentifier);
        if (isset($this->alreadyCreatedNodeAggregateIdentifiers[(string)$nodeAggregateIdentifier])) {
            // a Node of this NodeAggregate already exists; we create a Node
            $event = new NodeWasAddedToAggregate(
                $this->contentStreamIdentifier,
                $nodeAggregateIdentifier,
                $nodeTypeName,
                $dimensionSpacePoint,
                $visibleInDimensionSpacePoints,
                $nodeIdentifier,
                $parentNodeIdentifier,
                $nodeName,
                PropertyValues::fromArray($propertyValues)
            );
        } else {
            // first time a Node of this NodeAggregate is created
            $event = new NodeAggregateWithNodeWasCreated(
                $this->contentStreamIdentifier,
                $nodeAggregateIdentifier,
                $nodeTypeName,
                $dimensionSpacePoint,
                $visibleInDimensionSpacePoints,
                $nodeIdentifier,
                $parentNodeIdentifier,
                $nodeName,
                PropertyValues::fromArray($propertyValues)
            );
        }
        $this->commitEvent($streamName, $event);

        // publish reference edges
        foreach ($propertyReferences as $propertyName => $references) {
            $streamName = $this->contentStreamName('NodeAggregate:' . $nodeIdentifier);
            $event = new NodeReferencesWereSet(
                $this->contentStreamIdentifier,
                $visibleInDimensionSpacePoints,
                $nodeIdentifier,
                PropertyName::fromString($propertyName),
                $references
            );
            $this->commitEvent($streamName, $event);
        }

        $this->alreadyCreatedNodeAggregateIdentifiers[(string)$nodeAggregateIdentifier] = true;
    }

    protected function contentStreamName($suffix = null): StreamName
    {
        return StreamName::fromString('Neos.ContentRepository:ContentStream:' . $this->contentStreamIdentifier . ($suffix ? ':' . $suffix : ''));
    }

    private function processPropertyValues(NodeData $nodeData)
    {
        $properties = [];
        foreach ($nodeData->getProperties() as $propertyName => $propertyValue) {
            $type = $nodeData->getNodeType()->getPropertyType($propertyName);

            if ($type === 'reference' || $type === 'references') {
                // TODO: support other types than string
                continue;
            }
            $this->encodeObjectReference($propertyValue);
            $properties[$propertyName] = new PropertyValue($propertyValue, $type);
        }

        return $properties;
    }

    protected function encodeObjectReference(&$value)
    {
        if (is_array($value)) {
            foreach ($value as &$item) {
                $this->encodeObjectReference($item);
            }
        }

        if (!is_object($value)) {
            return;
        }

        $propertyClassName = TypeHandling::getTypeForValue($value);

        if ($value instanceof \DateTimeInterface) {
            $value = [
                'date' => $value->format('Y-m-d H:i:s.u'),
                'timezone' => $value->format('e'),
                'dateFormat' => 'Y-m-d H:i:s.u'
            ];
        } else {
            $value = [
                '__flow_object_type' => $propertyClassName,
                '__identifier' => $this->persistenceManager->getIdentifierByObject($value)
            ];
        }
    }

    private function processPropertyReferences(NodeData $nodeData)
    {
        $references = [];
        foreach ($nodeData->getProperties() as $propertyName => $propertyValue) {
            $type = $nodeData->getNodeType()->getPropertyType($propertyName);
            try {
                if ($type === 'reference') {
                    if ($propertyValue) {
                        $references[$propertyName] = [NodeAggregateIdentifier::fromString($propertyValue)];
                    }
                }
                if ($type === 'references' && is_array($propertyValue)) {
                    $references[$propertyName] = array_map(function ($identifier) {
                        return NodeAggregateIdentifier::fromString($identifier);
                    }, $propertyValue);
                }
            } catch (\Exception $e) {
                echo "SKIPPING property reference with value $propertyValue\n";
            }
        }
        return $references;
    }

    private function findParentNodeIdentifier($parentPath, DimensionSpacePoint $dimensionSpacePoint): ?NodeIdentifier
    {
        if ($parentPath === '/sites') {
            return $this->sitesRootNodeIdentifier;
        }

        while ($dimensionSpacePoint !== null) {
            $key = $parentPath . '__' . $dimensionSpacePoint->getHash();
            if (isset($this->nodeIdentifiers[$key])) {
                return $this->nodeIdentifiers[$key];
            }

            $dimensionSpacePoint = $this->interDimensionalFallbackGraph->getPrimaryGeneralization($dimensionSpacePoint);
        }

        return null;
    }

    private function recordNodeIdentifier(NodePath $nodePath, DimensionSpacePoint $dimensionSpacePoint, NodeIdentifier $nodeIdentifier)
    {
        $key = $nodePath . '__' . $dimensionSpacePoint->getHash();
        if (isset($this->nodeIdentifiers[$key])) {
            throw new \RuntimeException('TODO: node identifier ' . $key . 'already known!!!');
        }
        $this->nodeIdentifiers[$key] = $nodeIdentifier;
    }

    private function findOtherExistingDimensionSpacePointsForNodeData(NodeData $nodeData): DimensionSpacePointSet
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $query = $queryBuilder->select('n')
            ->from(NodeData::class, 'n')
            ->where('n.workspace = :workspace')
            ->andWhere('n.movedTo IS NULL OR n.removed = :removed')
            ->andWhere('n.identifier = :identifier')
            ->setParameter('workspace', 'live')
            ->setParameter('removed', false, \PDO::PARAM_BOOL)
            ->setParameter('identifier', $nodeData->getIdentifier());

        $points = [];
        foreach ($query->getQuery()->getResult() as $relatedNodeData) {
            if ($relatedNodeData === $nodeData) {
                // skip current element
                continue;
            }

            /** @var $relatedNodeData NodeData */
            $points[] = DimensionSpacePoint::fromLegacyDimensionArray($relatedNodeData->getDimensionValues());
        }

        return new DimensionSpacePointSet($points);
    }

    private function createRootWorkspace()
    {
        $streamName = StreamName::fromString('Neos.ContentRepository:Workspace:live');
        $event = new RootWorkspaceWasCreated(
            new WorkspaceName('live'),
            new WorkspaceTitle('Live'),
            new WorkspaceDescription(''),
            UserIdentifier::forSystemUser(),
            $this->contentStreamIdentifier
        );
        $this->commitEvent($streamName, $event);
    }

    private function createRootNode()
    {
        $dimensionSpacePointSet = $this->contentDimensionZookeeper->getAllowedDimensionSubspace();
        $event = new RootNodeWasCreated(
            $this->contentStreamIdentifier,
            $this->sitesRootNodeIdentifier,
            RootNodeIdentifiers::rootNodeAggregateIdentifier(),
            NodeTypeName::fromString('Neos.Neos:Sites'),
            $dimensionSpacePointSet,
            UserIdentifier::forSystemUser()
        );
        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($this->contentStreamIdentifier)->getEventStreamName();
        $this->commitEvent($streamName, $event);
    }

    private function commitEvent(StreamName $streamName, DomainEventInterface $event): void
    {
        $event = EventWithIdentifier::create($event);
        $eventStore = $this->eventStoreManager->getEventStoreForStreamName($streamName);
        $publishedEvents = DomainEvents::withSingleEvent($event);
        $eventStore->commit($streamName, $publishedEvents);

        $this->commandResult = CommandResult::fromPublishedEvents(
            $publishedEvents
        );
    }
}
