<?php


namespace Neos\EventSourcedContentRepository\Standalone\DependencyInjection\Overrides;

use Neos\Flow\Configuration\ConfigurationManager;

class SlimConfigurationManager extends ConfigurationManager
{
    /**
     * @var array
     */
    protected $nodeTypeConfiguration;

    public function __construct(array $nodeTypeConfiguration)
    {
        $this->nodeTypeConfiguration = $nodeTypeConfiguration;
    }

    public function getConfiguration(string $configurationType, string $configurationPath = null)
    {
        if ($configurationType !== 'NodeTypes') {
            throw new \RuntimeException('SlimConfigurationManager can only handle Configuration of type "NodeTypes"');
        }

        if ($configurationPath !== null) {
            throw new \RuntimeException('SlimConfigurationManager can only handle empty $configurationPath');
        }

        return $this->nodeTypeConfiguration;
    }
}
