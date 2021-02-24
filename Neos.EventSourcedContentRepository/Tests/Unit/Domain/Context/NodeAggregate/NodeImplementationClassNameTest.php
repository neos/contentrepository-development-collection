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

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Exception\NodeConfigurationException;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\TraversableNode;
use Neos\EventSourcedContentRepository\Tests\Unit\Fixtures\ExtendedValidTraversableNode;
use Neos\EventSourcedContentRepository\Tests\Unit\Fixtures\InvalidTraversableNode;
use Neos\EventSourcedContentRepository\Tests\Unit\Fixtures\InvalidTraversableNodeWithLegacySupport;
use Neos\EventSourcedContentRepository\Tests\Unit\Fixtures\ValidTraversableNode;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for the node implementation class name resolver
 */
final class NodeImplementationClassNameTest extends TestCase
{

    /**
     * @param NodeType $nodeType
     * @param string $expectedClassName
     * @dataProvider validNodeTypeProvider
     * @throws NodeConfigurationException
     */
    public function testFromNodeTypeReturnsCorrectImplementationClassNameForValidParameters(NodeType $nodeType, string $expectedClassName): void
    {
        Assert::assertSame($expectedClassName, NodeImplementationClassName::forNodeType($nodeType));
    }

    public function validNodeTypeProvider(): array
    {
        return [
            [
                new NodeType('Neos.ContentRepository:Test', [], ['class' => ValidTraversableNode::class]),
                ValidTraversableNode::class
            ],
            [
                new NodeType('Neos.ContentRepository:Test', [], ['class' => ExtendedValidTraversableNode::class]),
                ExtendedValidTraversableNode::class
            ],
            [
                new NodeType('Neos.ContentRepository:Test', [], []),
                TraversableNode::class
            ],
        ];
    }

    /**
     * @param NodeType $nodeType
     * @param string $expectedException
     * @dataProvider invalidNodeTypeProvider
     */
    public function testFromNodeTypeThrowsExceptionForValidParameters(NodeType $nodeType, string $expectedException): void
    {
        $actualException = null;
        try {
            NodeImplementationClassName::forNodeType($nodeType);
        } catch(\Exception $exception) {
            $actualException = $exception;
        }

        Assert::assertInstanceOf($expectedException, $actualException);
    }

    public function invalidNodeTypeProvider(): array
    {
        return [
            [
                new NodeType('Neos.ContentRepository:Test', [], ['class' => '\\Neos\\ContentRepository\\Tests\\Unit\\Fixtures\\IDoNotExist']),
                NodeConfigurationException::class
            ],
            [
                new NodeType('Neos.ContentRepository:Test', [], ['class' => InvalidTraversableNode::class]),
                NodeConfigurationException::class
            ],
            [
                new NodeType('Neos.ContentRepository:Test', [], ['class' => InvalidTraversableNodeWithLegacySupport::class]),
                NodeConfigurationException::class
            ],
        ];
    }
}
