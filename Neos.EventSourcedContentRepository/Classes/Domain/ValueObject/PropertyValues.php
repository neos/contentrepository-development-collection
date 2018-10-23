<?php

namespace Neos\EventSourcedContentRepository\Domain\ValueObject;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

final class PropertyValues implements \JsonSerializable
{
    /**
     * @var PropertyValue[]
     */
    private $values;

    /**
     * @param array $values
     */
    public function __construct(array $values)
    {
        // TODO
        $this->values = $values;
    }

    public function jsonSerialize()
    {
        return [
            'values' => $this->values,
        ];
    }
}
