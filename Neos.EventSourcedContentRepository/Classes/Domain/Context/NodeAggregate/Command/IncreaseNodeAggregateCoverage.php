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

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\Flow\Annotations as Flow;

/**
 * Increase the dimension space coverage of a node aggregate
 * * in a given content stream
 * * identified by a given node aggregate identifier
 * * using an already occupied (origin) dimension space point to define the node that is to cover the additional DSPs
 * * by a given set of dimension space points
 * * initiated by a given user
 * * recursive or not
 *
 * @Flow\Proxy(false)
 */
final class IncreaseNodeAggregateCoverage implements \JsonSerializable
{
    private ContentStreamIdentifier $contentStreamIdentifier;

    private NodeAggregateIdentifier $nodeAggregateIdentifier;

    private OriginDimensionSpacePoint $originDimensionSpacePoint;

    private DimensionSpacePointSet $additionalCoverage;

    private UserIdentifier $initiatingUserIdentifier;

    private bool $recursive;

    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        OriginDimensionSpacePoint $originDimensionSpacePoint,
        DimensionSpacePointSet $additionalCoverage,
        UserIdentifier $initiatingUserIdentifier,
        bool $recursive
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->originDimensionSpacePoint = $originDimensionSpacePoint;
        $this->additionalCoverage = $additionalCoverage;
        $this->initiatingUserIdentifier = $initiatingUserIdentifier;
        $this->recursive = $recursive;
    }

    public static function fromArray(array $array): self
    {
        return new self(
            ContentStreamIdentifier::fromString($array['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($array['nodeAggregateIdentifier']),
            OriginDimensionSpacePoint::fromArray($array['originDimensionSpacePoint']),
            DimensionSpacePointSet::fromArray($array['additionalCoverage']),
            UserIdentifier::fromString($array['initiatingUserIdentifier']),
            $array['recursive']
        );
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    public function getOriginDimensionSpacePoint(): OriginDimensionSpacePoint
    {
        return $this->originDimensionSpacePoint;
    }

    public function getAdditionalCoverage(): DimensionSpacePointSet
    {
        return $this->additionalCoverage;
    }

    public function getInitiatingUserIdentifier(): UserIdentifier
    {
        return $this->initiatingUserIdentifier;
    }

    public function getRecursive(): bool
    {
        return $this->recursive;
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'originDimensionSpacePoint' => $this->originDimensionSpacePoint,
            'additionalCoverage' => $this->additionalCoverage,
            'initiatingUserIdentifier' => $this->initiatingUserIdentifier,
            'recursive' => $this->recursive
        ];
    }
}
