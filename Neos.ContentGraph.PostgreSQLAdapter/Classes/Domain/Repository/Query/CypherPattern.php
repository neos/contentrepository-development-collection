<?php

/*
 * This file is part of the Neos.ContentGraph.PostgreSQLAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query;

use Neos\Flow\Annotations as Flow;

/**
 * A cypher pattern for queries against graphs or subgraphs, composed of path segments like
 * (name:Label {propertyName: 'propertyValue'})-[:RELATION_TYPE]->()
 *
 * @implements \IteratorAggregate<int,CypherNode>
 */
#[Flow\Proxy(false)]
final class CypherPattern implements \IteratorAggregate
{
    /**
     * @var \ArrayIterator<int,CypherNode>
     */
    private \ArrayIterator $iterator;

    private function __construct(
        /** @var array<int,CypherNode> */
        public readonly array $path
    ) {
        $this->iterator = new \ArrayIterator($path);
    }

    /**
     * @throws CypherPatternParserFailed
     */
    public static function tryFromString(string $string): ?self
    {
        return CypherPatternParser::parseString($string);
    }

    /**
     * @param array<int,CypherNode> $path
     */
    public static function fromArray(array $path): self
    {
        $expectedPathSegmentType = ExpectedCypherPatternPathSegmentType::initial();
        foreach ($path as $segmentNumber => $pathSegment) {
            // paths must built from (node)-[relation]-(node)-...-(node)
            if (!$expectedPathSegmentType->matches($pathSegment)) {
                throw new \InvalidArgumentException(
                    'Invalid cypher pattern path segment at position ' . $segmentNumber
                        . ': expected ' . $expectedPathSegmentType->value . ', got ' . get_class($pathSegment),
                    1645907271
                );
            }
            $expectedPathSegmentType = $expectedPathSegmentType->toggle();
        }
        if ($expectedPathSegmentType === ExpectedCypherPatternPathSegmentType::NODE) {
            throw new \InvalidArgumentException(
                'A cypher pattern must end with a node.',
                1645907409
            );
        }

        return new self($path);
    }

    /**
     * @return \ArrayIterator<int,CypherNode>
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->iterator;
    }
}
