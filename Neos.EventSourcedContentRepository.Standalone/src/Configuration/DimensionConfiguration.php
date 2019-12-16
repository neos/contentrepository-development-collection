<?php

namespace Neos\EventSourcedContentRepository\Standalone\Configuration;

final class DimensionConfiguration
{
    private function __construct()
    {
    }

    public static function createEmpty(): self
    {
        return new self();
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return [];
    }
}
