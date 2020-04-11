<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Redis\RedisClient\Graph;

// Taken and adapted from https://github.com/kjdev/php-redis-graph
class GraphNode
{
    public $label = '';
    public $alias = '';
    public $properties = [];
    /**
     * @var string|null
     */
    private $query;

    public static function create(string $type, array $properties): self
    {
        return new static($type, self::randomAlias(), null, $properties);
    }

    private static function randomAlias()
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randstring = '';
        for ($i = 0; $i < 10; $i++) {
            $randstring = $characters[rand(0, strlen($characters))];
        }
        return $randstring;
    }


    public static function createMatcher(string $type, \Closure $queryBuilder)
    {
        $alias = self::randomAlias();
        $query = $queryBuilder($alias);
        return new static($type, $alias, $query, []);
    }

    private function __construct(string $label, string $alias, ?string $query, array $properties = [])
    {
        $this->alias = $alias;
        $this->label = $label;
        $this->query = $query;
        $this->properties = $properties;
    }

    public function __toString()
    {
        $res = '(';
        if ($this->alias) {
            $res .= $this->alias;
        }
        if ($this->label) {
            $res .= ':' . $this->label;
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
        $res .= ')';

        if ($this->query) {
            $res = 'MATCH' . $res . ' WHERE ' . $this->query;
        }

        return $res;
    }
}
