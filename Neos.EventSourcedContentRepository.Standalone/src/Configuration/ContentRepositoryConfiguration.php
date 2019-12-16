<?php

namespace Neos\EventSourcedContentRepository\Standalone\Configuration;

final class ContentRepositoryConfiguration
{

    /**
     * @var string
     */
    private $vendorDirectory;

    /**
     * @var DatabaseConnectionParams
     */
    private $databaseConnectionParams;

    /**
     * @var NodeTypesConfiguration
     */
    private $nodeTypes;

    /**
     * @var DimensionConfiguration
     */
    private $dimensions;

    private function __construct(string $vendorDirectory, DatabaseConnectionParams $databaseConnectionParams, NodeTypesConfiguration $nodeTypes, DimensionConfiguration $dimensions)
    {
        $this->vendorDirectory = $vendorDirectory;
        $this->databaseConnectionParams = $databaseConnectionParams;
        $this->nodeTypes = $nodeTypes;
        $this->dimensions = $dimensions;
    }


    public static function create(string $vendorDirectory, DatabaseConnectionParams $databaseConnectionParams, NodeTypesConfiguration $nodeTypes, DimensionConfiguration $dimensions): self
    {
        return new self($vendorDirectory, $databaseConnectionParams, $nodeTypes, $dimensions);
    }

    /**
     * @return string
     */
    public function getVendorDirectory(): string
    {
        return $this->vendorDirectory;
    }

    /**
     * @return DatabaseConnectionParams
     */
    public function getDatabaseConnectionParams(): DatabaseConnectionParams
    {
        return $this->databaseConnectionParams;
    }

    /**
     * @return NodeTypesConfiguration
     */
    public function getNodeTypes(): NodeTypesConfiguration
    {
        return $this->nodeTypes;
    }

    /**
     * @return DimensionConfiguration
     */
    public function getDimensions(): DimensionConfiguration
    {
        return $this->dimensions;
    }
}
