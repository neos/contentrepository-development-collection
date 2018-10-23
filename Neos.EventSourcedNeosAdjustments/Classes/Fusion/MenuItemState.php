<?php

namespace Neos\EventSourcedNeosAdjustments\Fusion;

/**
 * The menu item state value object.
 */
final class MenuItemState
{
    const STATE_NORMAL = 'normal';
    const STATE_CURRENT = 'current';
    const STATE_ACTIVE = 'active';
    const STATE_ABSENT = 'absent';

    /**
     * @var string
     */
    protected $state;

    /**
     * @param string $state
     *
     * @throws Exception\InvalidMenuItemStateException
     */
    public function __construct(string $state)
    {
        if ($state !== self::STATE_NORMAL
            && $state !== self::STATE_CURRENT
            && $state !== self::STATE_ACTIVE
            && $state !== self::STATE_ABSENT) {
            throw new Exception\InvalidMenuItemStateException('"'.$state.'" is no valid menu item state', 1519668881);
        }

        $this->state = $state;
    }

    /**
     * @return MenuItemState
     */
    public static function normal(): self
    {
        return new self(self::STATE_NORMAL);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->state;
    }
}
