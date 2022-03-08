<?php declare(strict_types=1);
namespace Neos\ContentGraph\PostgreSQLAdapter\Test\Unit\Domain\Repository\Query;

use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query\CypherNode;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query\CypherNodeLabel;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query\CypherNodeLabels;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query\CypherPattern;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query\CypherPatternParser;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Repository\Query\CypherProperties;
use PHPUnit\Framework\TestCase;

final class NodeTest extends TestCase
{
    /**
     * @return \Iterator<string, array{string, CypherPattern}>
     */
    public function averageCaseProvider(): \Iterator
    {
        $input = '()';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                null,
                CypherNodeLabels::empty(),
                CypherProperties::empty()
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(name)';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                'name',
                CypherNodeLabels::empty(),
                CypherProperties::empty()
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(:Label)';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                null,
                CypherNodeLabels::fromArray([
                    CypherNodeLabel::fromString('Label')
                ]),
                CypherProperties::empty()
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(:`Acme.Site:Document`)';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                null,
                CypherNodeLabels::fromArray([
                    CypherNodeLabel::fromString('Acme.Site:Document')
                ]),
                CypherProperties::empty()
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(:Label:`Acme.Site:Document`)';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                null,
                CypherNodeLabels::fromArray([
                    CypherNodeLabel::fromString('Label'),
                    CypherNodeLabel::fromString('Acme.Site:Document')
                ]),
                CypherProperties::empty()
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(name:Label)';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                'name',
                CypherNodeLabels::fromArray([
                    CypherNodeLabel::fromString('Label')
                ]),
                CypherProperties::empty()
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '({propertyName: \'propertyValue\'})';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                null,
                CypherNodeLabels::empty(),
                CypherProperties::fromArray([
                    'propertyName' => 'propertyValue'
                ])
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(name {propertyName: \'propertyValue\'})';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                'name',
                CypherNodeLabels::empty(),
                CypherProperties::fromArray([
                    'propertyName' => 'propertyValue'
                ])
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(name:Label {propertyName: \'propertyValue\'})';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                'name',
                CypherNodeLabels::fromArray([
                    CypherNodeLabel::fromString('Label')
                ]),
                CypherProperties::fromArray([
                    'propertyName' => 'propertyValue'
                ])
            )
        ]);
        yield $input => [$input, $expectedPattern];

        $input = '(name:`Acme.Site:Document` {propertyName: \'propertyValue\'})';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                'name',
                CypherNodeLabels::fromArray([
                    CypherNodeLabel::fromString('Acme.Site:Document')
                ]),
                CypherProperties::fromArray([
                    'propertyName' => 'propertyValue'
                ])
            )
        ]);
        yield $input => [$input, $expectedPattern];

        /*
        $input = '(name:Label,`Acme.Site:Document` {stringProperty: \'text\', intProperty: 42, floatProperty: 84.72, falseProperty: false, trueProperty: true, nullProperty: null})';
        $expectedPattern = CypherPattern::fromArray([
            new CypherNode(
                'name',
                CypherNodeLabels::fromArray([
                    CypherNodeLabel::fromString('Label'),
                    CypherNodeLabel::fromString('Acme.Site:Document')
                ]),
                CypherProperties::fromArray([
                    'stringProperty' => 'text',
                    'intProperty' => 42,
                    'floatProperty' => 84.72,
                    'falseProperty' => false,
                    'trueProperty' => true,
                ])
            )
        ]);*/
        yield $input => [$input, $expectedPattern];
    }

    /**
     * @test
     * @small
     * @dataProvider averageCaseProvider
     */
    public function testAverageCase(string $input, CypherPattern $expectedPattern): void
    {
        $actualPattern = CypherPattern::tryFromString($input);

        $this->assertEquals($expectedPattern, $actualPattern, (string)$actualPattern->path[0]);
    }
}
