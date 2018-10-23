<?php

namespace Neos\EventSourcedContentRepository\Domain\ValueObject;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Title of a workspace.
 */
class WorkspaceTitle implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $title;

    /**
     * @param string $title
     */
    public function __construct(string $title)
    {
        $this->setTitle($title);
    }

    /**
     * @param string $title
     */
    protected function setTitle(string $title)
    {
        if (preg_match('/^[\p{L}\p{P}\d \.]{1,200}$/u', $title) !== 1) {
            throw new \InvalidArgumentException('Invalid workspace title given.', 1505827170288);
        }
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->title;
    }
}
