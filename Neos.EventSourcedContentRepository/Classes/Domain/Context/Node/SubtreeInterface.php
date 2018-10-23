<?php
/**
 * Created by IntelliJ IDEA.
 * User: sebastian
 * Date: 28.02.18
 * Time: 15:04.
 */

namespace Neos\EventSourcedContentRepository\Domain\Context\Node;

use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;

/**
 * Interface SubtreeInterface.
 */
interface SubtreeInterface
{
    public function getLevel(): int;

    public function getNode(): ?NodeInterface;

    /**
     * @return SubtreeInterface[]
     */
    public function getChildren(): array;
}
