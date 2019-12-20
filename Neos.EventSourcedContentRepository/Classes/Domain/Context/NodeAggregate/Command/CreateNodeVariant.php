<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;

/**
 * Create a variant of a node in a content stream
 *
 * Copy a node to another dimension space point respecting further variation mechanisms
 */
final class CreateNodeVariant implements \JsonSerializable
{
    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifier;

    /**
     * @var OriginDimensionSpacePoint
     */
    private $sourceOrigin;

    /**
     * @var OriginDimensionSpacePoint
     */
    private $targetOrigin;

    /**
     * @var UserIdentifier
     */
    private $initiatingUserIdentifier;

    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        OriginDimensionSpacePoint $sourceOrigin,
        OriginDimensionSpacePoint $targetOrigin,
        UserIdentifier $initiatingUserIdentifier
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->sourceOrigin = $sourceOrigin;
        $this->targetOrigin = $targetOrigin;
        $this->initiatingUserIdentifier = $initiatingUserIdentifier;
    }

    public static function fromArray(array $array): self
    {
        return new static(
            ContentStreamIdentifier::fromString($array['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($array['nodeAggregateIdentifier']),
            OriginDimensionSpacePoint::fromArray($array['sourceOrigin']),
            OriginDimensionSpacePoint::fromArray($array['targetOrigin']),
            UserIdentifier::fromString($array['initiatingUserIdentifier'])
        );
    }

    /**
     * @return ContentStreamIdentifier
     */
    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    /**
     * @return NodeAggregateIdentifier
     */
    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    /**
     * @return OriginDimensionSpacePoint
     */
    public function getSourceOrigin(): OriginDimensionSpacePoint
    {
        return $this->sourceOrigin;
    }

    /**
     * @return OriginDimensionSpacePoint
     */
    public function getTargetOrigin(): OriginDimensionSpacePoint
    {
        return $this->targetOrigin;
    }

    public function getInitiatingUserIdentifier(): UserIdentifier
    {
        return $this->initiatingUserIdentifier;
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'sourceOrigin' => $this->sourceOrigin,
            'targetOrigin' => $this->targetOrigin,
            'initiatingUserIdentifier' => $this->initiatingUserIdentifier
        ];
    }
}
