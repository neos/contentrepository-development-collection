<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient\Graph;

// Taken and adapted from https://github.com/kjdev/php-redis-graph
class GraphEdge
{
    public $src = null;
    public $dest = null;
    public $relation = null;
    public $properties = [];

    public static function create(GraphNode $src,
                                  GraphNode $dest,
                                  $relation,
                                  array $properties = []): self
    {
        return new static($src, $dest, $relation, $properties);
    }

    private function __construct(
        GraphNode $src,
        GraphNode $dest,
        $relation = null,
        array $properties = []
    )
    {
        $this->src = $src;
        $this->dest = $dest;
        $this->relation = $relation;
        $this->properties = $properties;
    }

    public function __toString()
    {
        // Source node.
        $res = '(' . $this->src->alias . ')';

        // Edge
        $res .= '-[';
        if ($this->relation) {
            $res .= ':' . $this->relation;
        }
        if ($this->properties) {
            $props = [];
            foreach ($this->properties as $key => $val) {
                if (is_int($val) || is_double($val)) {
                    $props[] = $key . ':' . $val;
                } else {
                    $props[] = $key . ':"' . trim((string)$val, '"') . '"';
                }
            }
            $res .= '{' . implode(',', $props) . '}';
        }
        $res .= ']->';

        // Dest node.
        $res .= '(' . $this->dest->alias . ')';

        return $res;
    }
}
