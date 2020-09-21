<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\Exception\DimensionSpacePointNotFound;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamRepository;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\Exception\ContentStreamDoesNotExistYet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\DimensionSpacePointIsAlreadyOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\DimensionSpacePointIsNotYetOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateCurrentlyDisablesDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateCurrentlyDoesNotDisableDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateCurrentlyDoesNotExist;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateIsDescendant;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateIsRoot;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateIsTethered;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregatesTypeIsAmbiguous;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeAggregateCurrentlyExists;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeNameIsAlreadyCovered;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeNameIsAlreadyOccupied;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeTypeDoesNotDeclareProperty;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeTypeIsNotOfTypeRoot;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeTypeIsOfTypeRoot;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception\NodeTypeNotFound;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeAggregate;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;

trait ConstraintChecks
{
    abstract protected function getContentGraph(): ContentGraphInterface;

    abstract protected function getContentStreamRepository(): ContentStreamRepository;

    abstract protected function getNodeTypeManager(): NodeTypeManager;

    abstract protected function getAllowedDimensionSubspace(): DimensionSpacePointSet;

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @throws ContentStreamDoesNotExistYet
     */
    protected function requireContentStreamToExist(ContentStreamIdentifier $contentStreamIdentifier): void
    {
        $contentStream = $this->getContentStreamRepository()->findContentStream($contentStreamIdentifier);
        if (!$contentStream) {
            throw new ContentStreamDoesNotExistYet('Content stream "' . $contentStreamIdentifier . " does not exist yet.", 1521386692);
        }
    }

    /**
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws DimensionSpacePointNotFound
     */
    protected function requireDimensionSpacePointToExist(DimensionSpacePoint $dimensionSpacePoint): void
    {
        if (!$this->getAllowedDimensionSubspace()->contains($dimensionSpacePoint)) {
            throw new DimensionSpacePointNotFound(sprintf('%s was not found in the allowed dimension subspace', $dimensionSpacePoint), 1520260137);
        }
    }

    /**
     * @param NodeTypeName $nodeTypeName
     * @return NodeType
     * @throws NodeTypeNotFound
     */
    protected function requireNodeType(NodeTypeName $nodeTypeName): NodeType
    {
        try {
            return $this->getNodeTypeManager()->getNodeType((string)$nodeTypeName);
        } catch (NodeTypeNotFoundException $exception) {
            throw new NodeTypeNotFound('Node type "' . $nodeTypeName . '" is unknown to the node type manager.', 1541671070);
        }
    }

    /**
     * @param NodeType $nodeType
     * @throws NodeTypeIsNotOfTypeRoot
     */
    protected function requireNodeTypeToBeOfTypeRoot(NodeType $nodeType): void
    {
        if (!$nodeType->isOfType(NodeTypeName::ROOT_NODE_TYPE_NAME)) {
            throw new NodeTypeIsNotOfTypeRoot('Node type "' . $nodeType . '" is not of type root.', 1541765701);
        }
    }

    /**
     * @param NodeType $nodeType
     * @throws NodeTypeIsOfTypeRoot
     */
    protected function requireNodeTypeToNotBeOfTypeRoot(NodeType $nodeType): void
    {
        if ($nodeType->isOfType(NodeTypeName::ROOT_NODE_TYPE_NAME)) {
            throw new NodeTypeIsOfTypeRoot('Node type "' . $nodeType->getName() . '" is of type root.', 1541765806);
        }
    }

    /**
     * @param NodeType $nodeType
     * @param PropertyName $propertyName
     * @throws NodeTypeDoesNotDeclareProperty
     */
    protected function requireNodeTypeToDeclareProperty(NodeType $nodeType, PropertyName $propertyName): void
    {
        if (!isset($nodeType->getProperties()[(string) $propertyName])) {
            throw NodeTypeDoesNotDeclareProperty::butWasSupposedTo(NodeTypeName::fromString($nodeType->getName()), $propertyName);
        }
    }

    /**
     * @param NodeType $nodeType
     * @throws NodeTypeNotFoundException
     */
    protected function requireTetheredDescendantNodeTypesToExist(NodeType $nodeType): void
    {
        foreach ($nodeType->getAutoCreatedChildNodes() as $childNodeType) {
            $this->requireTetheredDescendantNodeTypesToExist($childNodeType);
        }
    }

    /**
     * @param NodeType $nodeType
     * @throws NodeTypeIsOfTypeRoot
     */
    protected function requireTetheredDescendantNodeTypesToNotBeOfTypeRoot(NodeType $nodeType): void
    {
        foreach ($nodeType->getAutoCreatedChildNodes() as $tetheredChildNodeType) {
            if ($tetheredChildNodeType->isOfType(NodeTypeName::ROOT_NODE_TYPE_NAME)) {
                throw new NodeTypeIsOfTypeRoot('Node type "' . $nodeType->getName() . '" for tethered descendant is of type root.', 1541767062);
            }
            $this->requireTetheredDescendantNodeTypesToNotBeOfTypeRoot($tetheredChildNodeType);
        }
    }

    /**
     * NodeType and NodeName must belong together to the same node, which is the to-be-checked one.
     *
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeType $nodeType
     * @param NodeName|null $nodeName
     * @param array|NodeAggregateIdentifier[] $parentNodeAggregateIdentifiers
     * @throws NodeConstraintException
     */
    protected function requireConstraintsImposedByAncestorsAreMet(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeType $nodeType,
        ?NodeName $nodeName,
        array $parentNodeAggregateIdentifiers
    ): void
    {
        foreach ($parentNodeAggregateIdentifiers as $parentNodeAggregateIdentifier) {
            $parentAggregate = $this->requireProjectedNodeAggregate($contentStreamIdentifier, $parentNodeAggregateIdentifier);
            try {
                $parentsNodeType = $this->requireNodeType($parentAggregate->getNodeTypeName());
                $this->requireNodeTypeConstraintsImposedByParentToBeMet($parentsNodeType, $nodeName, $nodeType);
            } catch (NodeTypeNotFound $e) {
                // skip constraint check; Once the parent is changed to be of an available type,
                // the constraint checks are executed again. See handleChangeNodeAggregateType
            }

            foreach ($this->getContentGraph()->findParentNodeAggregates($contentStreamIdentifier, $parentNodeAggregateIdentifier) as $grandParentNodeAggregate) {
                try {
                    $grandParentsNodeType = $this->requireNodeType($grandParentNodeAggregate->getNodeTypeName());
                    $this->requireNodeTypeConstraintsImposedByGrandparentToBeMet($grandParentsNodeType, $parentAggregate->getNodeName(), $nodeType);

                } catch (NodeTypeNotFound $e) {
                    // skip constraint check; Once the grand parent is changed to be of an available type,
                    // the constraint checks are executed again. See handleChangeNodeAggregateType
                }
            }
        }
    }

    protected function requireNodeTypeConstraintsImposedByParentToBeMet(NodeType $parentsNodeType, ?NodeName $nodeName, NodeType $nodeType): void
    {
        // !!! IF YOU ADJUST THIS METHOD, also adjust the method below.
        if (!$parentsNodeType->allowsChildNodeType($nodeType)) {
            throw new NodeConstraintException('Node type "' . $nodeType . '" is not allowed for child nodes of type ' . $parentsNodeType->getName());
        }
        if ($nodeName
            && $parentsNodeType->hasAutoCreatedChildNode($nodeName)
            && $parentsNodeType->getTypeOfAutoCreatedChildNode($nodeName)->getName() !== $nodeType->getName()) {
            throw new NodeConstraintException('Node type "' . $nodeType . '" does not match configured "' . $parentsNodeType->getTypeOfAutoCreatedChildNode($nodeName)->getName()
                . '" for auto created child nodes for parent type "' . $parentsNodeType . '" with name "' . $nodeName . '"');
        }
    }

    protected function areNodeTypeConstraintsImposedByParentValid(NodeType $parentsNodeType, ?NodeName $nodeName, NodeType $nodeType): bool
    {
        // !!! IF YOU ADJUST THIS METHOD, also adjust the method above.
        if (!$parentsNodeType->allowsChildNodeType($nodeType)) {
            return false;
        }
        if ($nodeName
            && $parentsNodeType->hasAutoCreatedChildNode($nodeName)
            && $parentsNodeType->getTypeOfAutoCreatedChildNode($nodeName)->getName() !== $nodeType->getName()) {
            return false;
        }
        return true;
    }

    protected function requireNodeTypeConstraintsImposedByGrandparentToBeMet(NodeType $grandParentsNodeType, NodeName $parentNodeName, NodeType $nodeType): void
    {
        if (!$this->areNodeTypeConstraintsImposedByGrandparentValid($grandParentsNodeType, $parentNodeName, $nodeType)) {
            throw new NodeConstraintException('Node type "' . $nodeType . '" is not allowed below tethered child nodes "' . $parentNodeName
                . '" of nodes of type "' . $grandParentsNodeType->getName() . '"', 1520011791);
        }
    }

    protected function areNodeTypeConstraintsImposedByGrandparentValid(NodeType $grandParentsNodeType, NodeName $parentNodeName, NodeType $nodeType): bool
    {
        if ($parentNodeName
            && $grandParentsNodeType->hasAutoCreatedChildNode($parentNodeName)
            && !$grandParentsNodeType->allowsGrandchildNodeType((string)$parentNodeName, $nodeType)) {
            return false;
        }
        return true;
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @return NodeAggregate
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws NodeAggregateCurrentlyDoesNotExist
     */
    protected function requireProjectedNodeAggregate(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): ReadableNodeAggregateInterface
    {
        $nodeAggregate = $this->getContentGraph()->findNodeAggregateByIdentifier($contentStreamIdentifier, $nodeAggregateIdentifier);

        if (!$nodeAggregate) {
            throw new NodeAggregateCurrentlyDoesNotExist('Node aggregate "' . $nodeAggregateIdentifier . '" does currently not exist.', 1541678486);
        }

        return $nodeAggregate;
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws NodeAggregateCurrentlyExists
     */
    protected function requireProjectedNodeAggregateToNotExist(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier
    ): void
    {
        $nodeAggregate = $this->getContentGraph()->findNodeAggregateByIdentifier($contentStreamIdentifier, $nodeAggregateIdentifier);

        if ($nodeAggregate) {
            throw new NodeAggregateCurrentlyExists('Node aggregate "' . $nodeAggregateIdentifier . '" does currently exist, but should not.', 1541687645);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint
     */
    protected function requireNodeAggregateToCoverDimensionSpacePoint(
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePoint $dimensionSpacePoint
    ): void
    {
        if (!$nodeAggregate->coversDimensionSpacePoint($dimensionSpacePoint)) {
            throw new NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint('Node aggregate "' . $nodeAggregate->getIdentifier() . '" does currently not cover dimension space point ' . json_encode($dimensionSpacePoint) . '.', 1541678877);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePointSet $dimensionSpacePointSet
     * @throws NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet
     */
    protected function requireNodeAggregateToCoverDimensionSpacePoints(
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePointSet $dimensionSpacePointSet
    ): void
    {
        if (!$nodeAggregate->getCoveredDimensionSpacePoints()->getPointHashes() === $dimensionSpacePointSet->getPointHashes()) {
            throw NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet::butWasSupposedTo(
                $nodeAggregate->getIdentifier(),
                $dimensionSpacePointSet,
                $nodeAggregate->getCoveredDimensionSpacePoints()
            );
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @throws NodeAggregateIsRoot
     */
    protected function requireNodeAggregateToNotBeRoot(ReadableNodeAggregateInterface $nodeAggregate): void
    {
        if ($nodeAggregate->isRoot()) {
            throw new NodeAggregateIsRoot('Node aggregate "' . $nodeAggregate->getIdentifier() . '" is classified as root.', 1554586860);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @throws NodeAggregateIsTethered
     */
    protected function requireNodeAggregateToBeUntethered(ReadableNodeAggregateInterface $nodeAggregate): void
    {
        if ($nodeAggregate->isTethered()) {
            throw new NodeAggregateIsTethered('Node aggregate "' . $nodeAggregate->getIdentifier() . '" is classified as tethered.', 1554587288);
        }
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param ReadableNodeAggregateInterface $referenceNodeAggregate
     * @throws NodeAggregateIsDescendant
     */
    protected function requireNodeAggregateToNotBeDescendant(
        ContentStreamIdentifier $contentStreamIdentifier,
        ReadableNodeAggregateInterface $nodeAggregate,
        ReadableNodeAggregateInterface $referenceNodeAggregate
    )
    {
        if ($nodeAggregate->getIdentifier()->equals($referenceNodeAggregate->getIdentifier())) {
            throw new NodeAggregateIsDescendant('Node aggregate "' . $nodeAggregate->getIdentifier() . '" is descendant of node aggregate "' . $referenceNodeAggregate->getIdentifier() . '"', 1554971124);
        }
        foreach ($this->getContentGraph()->findChildNodeAggregates($contentStreamIdentifier, $referenceNodeAggregate->getIdentifier()) as $childReferenceNodeAggregate) {
            $this->requireNodeAggregateToNotBeDescendant($contentStreamIdentifier, $nodeAggregate, $childReferenceNodeAggregate);
        }
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeName $nodeName
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param OriginDimensionSpacePoint $parentOriginDimensionSpacePoint
     * @param DimensionSpacePointSet $dimensionSpacePoints
     * @throws NodeNameIsAlreadyOccupied
     */
    protected function requireNodeNameToBeUnoccupied(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeName $nodeName,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        OriginDimensionSpacePoint $parentOriginDimensionSpacePoint,
        DimensionSpacePointSet $dimensionSpacePoints
    ): void
    {
        $dimensionSpacePointsOccupiedByChildNodeName = $this->getContentGraph()->getDimensionSpacePointsOccupiedByChildNodeName(
            $contentStreamIdentifier,
            $nodeName,
            $parentNodeAggregateIdentifier,
            $parentOriginDimensionSpacePoint,
            $dimensionSpacePoints
        );
        if (count($dimensionSpacePointsOccupiedByChildNodeName) > 0) {
            throw new NodeNameIsAlreadyOccupied('Child node name "' . $nodeName . '" is already occupied for parent "' . $parentNodeAggregateIdentifier . '" in dimension space points ' . $dimensionSpacePointsOccupiedByChildNodeName);
        }
    }

    /**
     * @param ContentStreamIdentifier $contentStreamIdentifier
     * @param NodeName $nodeName
     * @param NodeAggregateIdentifier $parentNodeAggregateIdentifier
     * @param DimensionSpacePointSet $dimensionSpacePointsToBeCovered
     * @throws NodeNameIsAlreadyCovered
     */
    protected function requireNodeNameToBeUncovered(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeName $nodeName,
        NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        DimensionSpacePointSet $dimensionSpacePointsToBeCovered
    ): void
    {
        $childNodeAggregates = $this->getContentGraph()->findChildNodeAggregatesByName($contentStreamIdentifier, $parentNodeAggregateIdentifier, $nodeName);
        foreach ($childNodeAggregates as $childNodeAggregate) {
            $alreadyCoveredDimensionSpacePoints = $childNodeAggregate->getCoveredDimensionSpacePoints()->getIntersection($dimensionSpacePointsToBeCovered);
            if (!empty($alreadyCoveredDimensionSpacePoints)) {
                throw new NodeNameIsAlreadyCovered('Node name "' . $nodeName . '" is already covered in dimension space points ' . $alreadyCoveredDimensionSpacePoints . ' by node aggregate "' . $childNodeAggregate->getIdentifier() . '".');
            }
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws DimensionSpacePointIsNotYetOccupied
     */
    protected function requireNodeAggregateToOccupyDimensionSpacePoint(ReadableNodeAggregateInterface $nodeAggregate, DimensionSpacePoint $dimensionSpacePoint)
    {
        if (!$nodeAggregate->occupiesDimensionSpacePoint($dimensionSpacePoint)) {
            throw new DimensionSpacePointIsNotYetOccupied('Dimension space point ' . json_encode($dimensionSpacePoint) . ' is not yet occupied by node aggregate "' . $nodeAggregate->getIdentifier() . '"', 1552595396);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws DimensionSpacePointIsAlreadyOccupied
     */
    protected function requireNodeAggregateToNotOccupyDimensionSpacePoint(ReadableNodeAggregateInterface $nodeAggregate, DimensionSpacePoint $dimensionSpacePoint)
    {
        if ($nodeAggregate->occupiesDimensionSpacePoint($dimensionSpacePoint)) {
            throw new DimensionSpacePointIsAlreadyOccupied('Dimension space point ' . json_encode($dimensionSpacePoint) . ' is already occupied by node aggregate "' . $nodeAggregate->getIdentifier() . '"', 1552595441);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws NodeAggregateCurrentlyDoesNotDisableDimensionSpacePoint
     */
    protected function requireNodeAggregateToDisableDimensionSpacePoint(
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePoint $dimensionSpacePoint
    ): void
    {
        if (!$nodeAggregate->disablesDimensionSpacePoint($dimensionSpacePoint)) {
            throw new NodeAggregateCurrentlyDoesNotDisableDimensionSpacePoint('Node aggregate "' . $nodeAggregate->getIdentifier() . '" currently does not disable dimension space point ' . json_encode($dimensionSpacePoint) . '.', 1557735431);
        }
    }

    /**
     * @param ReadableNodeAggregateInterface $nodeAggregate
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @throws NodeAggregateCurrentlyDisablesDimensionSpacePoint
     */
    protected function requireNodeAggregateToNotDisableDimensionSpacePoint(
        ReadableNodeAggregateInterface $nodeAggregate,
        DimensionSpacePoint $dimensionSpacePoint
    ): void
    {
        if ($nodeAggregate->disablesDimensionSpacePoint($dimensionSpacePoint)) {
            throw new NodeAggregateCurrentlyDisablesDimensionSpacePoint('Node aggregate "' . $nodeAggregate->getIdentifier() . '" currently disables dimension space point ' . json_encode($dimensionSpacePoint) . '.', 1555179563);
        }
    }
}
