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

use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddressFactory;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Neos\Flow\Security\Context;

/**
 * !!! Only needed for uncached Fusion segments; as in Fusion ContentCache, the PropertyMapper is used to serialize
 * and deserialize the context.
 *
 * @Flow\Scope("singleton")
 * @deprecated
 */
class NewNodeSerializer extends AbstractTypeConverter
{
    /**
     * @var array
     */
    protected $sourceTypes = [\Neos\ContentRepository\Domain\Projection\Content\NodeInterface::class];

    /**
     * @var string
     */
    protected $targetType = 'string';

    /**
     * @var integer
     */
    protected $priority = 1;


    /**
     * @Flow\Inject
     * @var NodeAddressFactory
     */
    protected $nodeAddressFactory;

    /**
     *
     */
    public function convertFrom($source, $targetType = null, array $subProperties = [], PropertyMappingConfigurationInterface $configuration = null)
    {
        // TODO: Move Node Address to CR
        return $this->nodeAddressFactory->createFromNode($source)->serializeForUri();
    }
}
