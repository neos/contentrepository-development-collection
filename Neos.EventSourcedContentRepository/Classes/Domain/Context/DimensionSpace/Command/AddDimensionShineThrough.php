<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\DimensionSpace\Command;

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
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\RebasableToOtherContentStreamsInterface;

/**
 * Add a Dimension Space Point Shine-Through; basically making all content available not just in the source(original) DSP, but also
 * in the target-DimensionSpacePoint.
 *
 * NOTE: the Source Dimension Space Point must be a parent of the target Dimension Space Point.
 *
 * This is needed if "de" exists, and you want to create a "de_CH" specialization.
 *
 * NOTE: the target dimension space point must not contain any content.
 */
final class AddDimensionShineThrough implements \JsonSerializable, RebasableToOtherContentStreamsInterface
{
    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var DimensionSpacePoint
     */
    private $source;

    /**
     * @var DimensionSpacePoint
     */
    private $target;

    public function __construct(ContentStreamIdentifier $contentStreamIdentifier, DimensionSpacePoint $source, DimensionSpacePoint $target)
    {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->source = $source;
        $this->target = $target;
    }

    public static function fromArray(array $array): self
    {
        return new static(
            ContentStreamIdentifier::fromString($array['contentStreamIdentifier']),
            DimensionSpacePoint::fromArray($array['source']),
            DimensionSpacePoint::fromArray($array['target'])
        );
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    /**
     * @return DimensionSpacePoint
     */
    public function getSource(): DimensionSpacePoint
    {
        return $this->source;
    }

    /**
     * @return DimensionSpacePoint
     */
    public function getTarget(): DimensionSpacePoint
    {
        return $this->target;
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'source' => $this->source,
            'target' => $this->target,
        ];
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): self
    {
        return new AddDimensionShineThrough(
            $targetContentStreamIdentifier,
            $this->source,
            $this->target
        );
    }
}