<?php

namespace Neos\EventSourcedNeosAdjustments\TypeConverter;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddress;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;

/**
 * An Object Converter for Node Addresses which can be used for routing (but also for other
 * purposes) as a plugin for the Property Mapper.
 *
 * @Flow\Scope("singleton")
 */
class NodeAddressToStringConverter extends AbstractTypeConverter
{
    /**
     * @var array
     */
    protected $sourceTypes = [NodeAddress::class];

    /**
     * @var string
     */
    protected $targetType = 'string';

    /**
     * @var int
     */
    protected $priority = 1;

    /**
     * @param NodeAddress                                $source
     * @param string                                     $targetType
     * @param array                                      $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     *
     * @return string
     */
    public function convertFrom($source, $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null): string
    {
        return $source->serializeForUri();
    }
}
