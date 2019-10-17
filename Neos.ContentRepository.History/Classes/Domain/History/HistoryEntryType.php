<?php
declare(strict_types=1);

namespace Neos\ContentRepository\History\Domain\History;

/*
 * This file is part of the Neos.ContentRepository.History package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\History\Domain\History\Exception\HistoryEntryTypeIsInvalid;
use Neos\Flow\Annotations as Flow;

/**
 * The history entry type value object
 */
final class HistoryEntryType implements \JsonSerializable
{
    const TYPE_CREATED = 'created';
    const TYPE_DISABLED = 'created';
    const TYPE_ENABLED = 'created';
    const TYPE_MODIFIED = 'modified';
    const TYPE_MOVED = 'moved';
    const TYPE_REFERENCED = 'referenced';
    const TYPE_REMOVED = 'removed';
    const TYPE_RENAMED = 'renamed';
    const TYPE_RETYPED = 'retyped';
    const TYPE_VARIED = 'varied';

    /**
     * @var string
     */
    private $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if ($value !== self::TYPE_CREATED
            && $value !== self::TYPE_DISABLED
            && $value !== self::TYPE_ENABLED
            && $value !== self::TYPE_MODIFIED
            && $value !== self::TYPE_MOVED
            && $value !== self::TYPE_REFERENCED
            && $value !== self::TYPE_REMOVED
            && $value !== self::TYPE_RENAMED
            && $value !== self::TYPE_RETYPED
            && $value !== self::TYPE_VARIED
        ) {
            throw HistoryEntryTypeIsInvalid::becauseItIsNotOneOfTheDefinedConstants($value);
        }

        return new static($value);
    }

    public static function created(): self
    {
        return new static(self::TYPE_CREATED);
    }

    public static function disabled(): self
    {
        return new static(self::TYPE_DISABLED);
    }

    public static function enabled(): self
    {
        return new static(self::TYPE_ENABLED);
    }

    public static function modified(): self
    {
        return new static(self::TYPE_MODIFIED);
    }

    public static function moved(): self
    {
        return new static(self::TYPE_MOVED);
    }

    public static function referenced(): self
    {
        return new static(self::TYPE_REFERENCED);
    }

    public static function removed(): self
    {
        return new static(self::TYPE_REMOVED);
    }

    public static function renamed(): self
    {
        return new static(self::TYPE_RENAMED);
    }

    public static function retyped(): self
    {
        return new static(self::TYPE_RETYPED);
    }

    public static function varied(): self
    {
        return new static(self::TYPE_VARIED);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
