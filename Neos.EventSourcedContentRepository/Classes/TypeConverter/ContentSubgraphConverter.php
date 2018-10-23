<?php

namespace Neos\EventSourcedContentRepository\TypeConverter;

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
use Neos\ContentRepository\Domain\ValueObject\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentSubgraphInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;

/**
 * An Object Converter for content subgraphs.
 *
 * @Flow\Scope("singleton")
 */
class ContentSubgraphConverter extends AbstractTypeConverter
{
    /**
     * @Flow\Inject
     *
     * @var ContentGraphInterface
     */
    protected $contentGraph;

    /**
     * @var array
     */
    protected $sourceTypes = ['string'];

    /**
     * @var string
     */
    protected $targetType = ContentSubgraphInterface::class;

    /**
     * @var int
     */
    protected $priority = 1;

    /**
     * @param mixed                                      $source
     * @param string                                     $targetType
     * @param array                                      $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     *
     * @return ContentSubgraphInterface
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null): ContentSubgraphInterface
    {
        $sourceArray = json_decode($source, true);

        return $this->contentGraph->getSubgraphByIdentifier(
            new ContentStreamIdentifier($sourceArray['contentStreamIdentifier']),
            new DimensionSpacePoint($sourceArray['dimensionSpacePoint']['coordinates'])
        );
    }
}
