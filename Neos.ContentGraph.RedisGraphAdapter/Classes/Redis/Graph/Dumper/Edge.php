<?php

namespace Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\Dumper;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class Edge
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $type;
    /**
     * @var array
     */
    private $properties;

    private function __construct(int $id, string $type, array $properties)
    {
        $this->id = $id;
        $this->type = $type;
        $this->properties = $properties;
    }

    public static function fromResult(array $in): self
    {
        assert($in[0][0] === 'id', '$in[0][0] === id');
        assert($in[1][0] === 'type', '$in[1][0] === type');
        // 2 = src_node
        // 3 = dest_node
        assert($in[4][0] === 'properties', '$in[4][0] === properties');

        $id = $in[0][1];
        $type = $in[1][1];
        $properties = [];
        foreach ($in[4][1] as $propertyLine) {
            $properties[$propertyLine[0]] = $propertyLine[1];
        }

        return new static($id, $type, $properties);
    }

    public function __toString()
    {
        $renderedProperties = [];
        foreach ($this->properties as $key => $element) {
            $renderedProperties[] = $key . ": '" . $element . "'";
        }

        $renderedPropertyString = implode(', ', $renderedProperties);
        if ($renderedPropertyString !== '') {
            $renderedPropertyString = ' {' . $renderedPropertyString . '}';
        }

        return " -(e{$this->id}:{$this->type}{$renderedPropertyString})-> ";
    }
}
