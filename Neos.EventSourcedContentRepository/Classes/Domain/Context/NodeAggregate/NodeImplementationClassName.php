<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface as LegacyNodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Exception\NodeConfigurationException;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\TraversableNode;
use Neos\Flow\Annotations as Flow;

/**
 * The node implementation class name resolver
 *
 * @Flow\Proxy(false)
 */
final class NodeImplementationClassName
{
    /**
     * @param NodeType $nodeType
     * @return string
     * @throws NodeConfigurationException
     */
    public static function forNodeType(NodeType $nodeType): string
    {
        $customClassName = $nodeType->getConfiguration('class');
        if (!empty($customClassName)) {
            if (!class_exists($customClassName)) {
                throw new NodeConfigurationException(
                    'The configured implementation class name "' . $customClassName . '" for NodeType "' . $nodeType . '" does not exist.',
                    1505805774
                );
            }

            $implementedInterfaces = class_implements($customClassName);
            if (!in_array(TraversableNodeInterface::class, $implementedInterfaces)) {
                if (in_array(LegacyNodeInterface::class, $implementedInterfaces)) {
                    throw new NodeConfigurationException('The configured implementation class name "' . $customClassName . '" for NodeType "' . $nodeType. '" inherits from the OLD (pre-event-sourced) NodeInterface; which is not supported anymore. Your custom Node class now needs to implement ' . TraversableNodeInterface::class . '.', 1520069750);
                }
                throw new NodeConfigurationException('The configured implementation class name "' . $customClassName . '" for NodeType "' . $nodeType. '" does not inherit from ' . TraversableNodeInterface::class . '.', 1406884014);
            }

            return $customClassName;
        } else {
            return TraversableNode::class;
        }
    }
}
