<?php

namespace Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\Dumper;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class Node
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var string[]
     */
    private $labels;
    /**
     * @var array
     */
    private $properties;

    private function __construct(int $id, array $labels, array $properties)
    {
        $this->id = $id;
        $this->labels = $labels;
        $this->properties = $properties;
    }

    public static function fromResult(array $in): self
    {
        assert($in[0][0] === 'id', '$in[0][0] === id');
        assert($in[1][0] === 'labels', '$in[1][0] === labels');
        assert($in[2][0] === 'properties', '$in[2][0] === properties');

        $id = $in[0][1];
        $labels = $in[1][1];
        $properties = [];
        foreach ($in[2][1] as $propertyLine) {
            $properties[$propertyLine[0]] = $propertyLine[1];
        }

        return new static($id, $labels, $properties);
    }

    public function __toString()
    {
        $renderedLabel = implode(',', $this->labels);

        $renderedProperties = [];
        foreach ($this->properties as $key => $element) {
            $renderedProperties[] = $key . ": '" . $element . "'";
        }

        $renderedPropertyString = implode(', ', $renderedProperties);
        if ($renderedPropertyString !== '') {
            $renderedPropertyString = ' {' . $renderedPropertyString . '}';
        }

        return "(n{$this->id}:{$renderedLabel}{$renderedPropertyString})";
    }
}
