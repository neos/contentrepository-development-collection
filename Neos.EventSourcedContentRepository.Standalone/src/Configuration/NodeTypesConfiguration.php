<?php

namespace Neos\EventSourcedContentRepository\Standalone\Configuration;

use Neos\Utility\Arrays;
use Symfony\Component\Yaml\Yaml;

final class NodeTypesConfiguration
{

    /**
     * @var array
     */
    private $configuration;

    private function __construct(array $nodeTypesArray)
    {
        $this->configuration = $nodeTypesArray;
    }

    public static function create(): self
    {
        return new self([
            // Same as defined in Neos.EventSourcedContentRepository/Configuration/NodeTypes.yaml
            'Neos.ContentRepository:Root' => [
                'abstract' => true
            ]
        ]);
    }

    public function add(array $nodeTypesAsArray): self
    {
        return new self(
            Arrays::arrayMergeRecursiveOverrule($this->configuration, $nodeTypesAsArray)
        );
    }

    public function addYaml(string $nodeTypesYaml): self
    {
        return $this->add(Yaml::parse($nodeTypesYaml));
    }

    public function addYamlFile(string $nodeTypesYamlFile): self
    {
        return $this->add(Yaml::parseFile($nodeTypesYamlFile));
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }
}
