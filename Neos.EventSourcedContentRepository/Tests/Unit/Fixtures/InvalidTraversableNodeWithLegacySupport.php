<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Tests\Unit\Fixtures;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\ArrayPropertyCollection;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Projection\Content\PropertyCollectionInterface;
use Neos\Flow\Annotations as Flow;

/**
 * An invalid traversable node implementation supporting the legacy API
 */
class InvalidTraversableNodeWithLegacySupport implements NodeInterface
{
    public function getNodeType(): NodeType
    {
        return new NodeType('Neos.ContentRepository:Test', [], []);
    }

    public function getProperties(): PropertyCollectionInterface
    {
        return new ArrayPropertyCollection([]);
    }

    public function getProperty($propertyName)
    {
        return null;
    }

    public function hasProperty($propertyName): bool
    {
        return false;
    }

    public function getLabel(): string
    {
        return '';
    }

    public function setName($newName)
    {
    }

    public function getName()
    {
    }

    public function setProperty($propertyName, $value)
    {
    }

    public function removeProperty($propertyName)
    {
    }

    public function getPropertyNames()
    {
    }

    public function setContentObject($contentObject)
    {
    }

    public function getContentObject()
    {
    }

    public function unsetContentObject()
    {
    }

    public function setNodeType(NodeType $nodeType)
    {
    }

    public function setHidden($hidden)
    {
    }

    public function isHidden()
    {
    }

    public function setHiddenBeforeDateTime(\DateTimeInterface $dateTime = null)
    {
    }

    public function getHiddenBeforeDateTime()
    {
    }

    public function setHiddenAfterDateTime(\DateTimeInterface $dateTime = null)
    {
    }

    public function getHiddenAfterDateTime()
    {
    }

    public function setHiddenInIndex($hidden)
    {
    }

    public function isHiddenInIndex()
    {
    }

    public function setAccessRoles(array $accessRoles)
    {
    }

    public function getAccessRoles()
    {
    }

    public function getPath()
    {
    }

    public function getContextPath()
    {
    }

    public function getDepth()
    {
    }

    public function setWorkspace(Workspace $workspace)
    {
    }

    public function getWorkspace()
    {
    }

    public function getIdentifier()
    {
    }

    public function setIndex($index)
    {
    }

    public function getIndex()
    {
    }

    public function getParent()
    {
    }

    public function getParentPath()
    {
    }

    public function createNode($name, NodeType $nodeType = null, $identifier = null)
    {
    }

    public function createSingleNode($name, NodeType $nodeType = null, $identifier = null)
    {
    }

    public function createNodeFromTemplate(NodeTemplate $nodeTemplate, $nodeName = null)
    {
    }

    public function getNode($path)
    {
    }

    public function getPrimaryChildNode()
    {
    }

    public function getChildNodes($nodeTypeFilter = null, $limit = null, $offset = null)
    {
    }

    public function hasChildNodes($nodeTypeFilter = null)
    {
    }

    public function remove()
    {
    }

    public function setRemoved($removed)
    {
    }

    public function isRemoved()
    {
    }

    public function isVisible()
    {
    }

    public function isAccessible()
    {
    }

    public function hasAccessRestrictions()
    {
    }

    public function isNodeTypeAllowedAsChildNode(NodeType $nodeType)
    {
    }

    public function moveBefore(NodeInterface $referenceNode)
    {
    }

    public function moveAfter(NodeInterface $referenceNode)
    {
    }

    public function moveInto(NodeInterface $referenceNode)
    {
    }

    public function copyBefore(NodeInterface $referenceNode, $nodeName)
    {
    }

    public function copyAfter(NodeInterface $referenceNode, $nodeName)
    {
    }

    public function copyInto(NodeInterface $referenceNode, $nodeName)
    {
    }

    public function getNodeData()
    {
    }

    public function getContext()
    {
    }

    public function getDimensions()
    {
    }

    public function createVariantForContext($context)
    {
    }

    public function isAutoCreated()
    {
    }

    public function getOtherNodeVariants()
    {
    }
}
