<?php
namespace Neos\EventSourcedNeosAdjustments\Eel\FlowQueryOperations;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Eel\FlowQuery\FlowQueryException;
use Neos\Eel\FlowQuery\Operations\AbstractOperation;
use Neos\EventSourcedContentRepository\ContentAccess\NodeAccessorManager;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\Utility\ObjectAccess;

/**
 * Used to access properties of a ContentRepository Node. If the property mame is
 * prefixed with _, internal node properties like start time, end time,
 * hidden are accessed.
 */
class PropertyOperation extends AbstractOperation
{
    /**
     * {@inheritdoc}
     *
     * @var string
     */
    protected static $shortName = 'property';

    /**
     * {@inheritdoc}
     *
     * @var integer
     */
    protected static $priority = 110;

    /**
     * {@inheritdoc}
     *
     * @var boolean
     */
    protected static $final = true;

    /**
     * @Flow\Inject
     * @var NodeAccessorManager
     */
    protected $nodeAccessorManager;

    /**
     * {@inheritdoc}
     *
     * We can only handle ContentRepository Nodes.
     *
     * @param mixed $context
     * @return boolean
     */
    public function canEvaluate($context)
    {
        return (isset($context[0]) && ($context[0] instanceof NodeInterface));
    }

    /**
     * {@inheritdoc}
     *
     * @param FlowQuery<int,mixed> $flowQuery the FlowQuery object
     * @param array<int,mixed> $arguments the arguments for this operation
     * @return mixed
     * @throws FlowQueryException
     */
    public function evaluate(FlowQuery $flowQuery, array $arguments)
    {
        if (empty($arguments[0])) {
            throw new FlowQueryException('property() does not support returning all attributes yet', 1332492263);
        } else {
            /** @var array<int,mixed> $context */
            $context = $flowQuery->getContext();
            $propertyPath = $arguments[0];

            if (!isset($context[0])) {
                return null;
            }

            /* @var $element NodeInterface */
            $element = $context[0];
            $nodeAccessor = $this->nodeAccessorManager->accessorFor(
                $element->getContentStreamIdentifier(),
                $element->getDimensionSpacePoint(),
                $element->getVisibilityConstraints()
            );
            if ($propertyPath === '_path') {
                return (string)$nodeAccessor->findNodePath($element);
            } elseif ($propertyPath[0] === '_') {
                return ObjectAccess::getPropertyPath($element, substr($propertyPath, 1));
            } else {
                // WORKAROUND: $nodeType->getPropertyType() is missing the "initialize" call,
                // so we need to trigger another method beforehand.
                $element->getNodeType()->getFullConfiguration();
                if ($element->getNodeType()->getPropertyType($propertyPath) === 'reference') {
                    $tmp = $nodeAccessor->findReferencedNodes($element, PropertyName::fromString($propertyPath));
                    $references = [];
                    foreach ($tmp as $reference) {
                        $references[] = $reference;
                    }
                    if (count($references) === 0) {
                        return null;
                    } else {
                        return $references[0];
                    }
                } elseif ($element->getNodeType()->getPropertyType($propertyPath) === 'references') {
                    return $nodeAccessor->findReferencedNodes($element, PropertyName::fromString($propertyPath));
                } else {
                    return $element->getProperty($propertyPath);
                }
            }
        }
    }
}
