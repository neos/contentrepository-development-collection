<?php
declare(strict_types=1);

namespace Neos\ContentGraph\RedisGraphAdapter\Redis\Graph;

// Taken and adapted from https://github.com/kjdev/php-redis-graph
class Graph
{
    const CLIENT_REDIS = 'Redis';
    const CLIENT_PREDIS = 'Predis\\Client';

    private $redis;
    private $client;
    public $name;
    public $nodes = [];
    public $edges = [];

    public function __construct($name, $redis)
    {
        if (!is_object($redis)) {
            throw new \RuntimeException('Redis client object not found.');
        }

        $this->client = get_class($redis);
        if (!in_array($this->client, [self::CLIENT_REDIS, self::CLIENT_PREDIS], true)) {
            throw new \RuntimeException('Unsupported Redis client.');
        }

        $this->name = $name;
        $this->redis = $redis;

        $response = $this->redisCommand('MODULE', 'LIST');
        if (!isset($response[0]) || !is_array($response[0])
            || !in_array('graph', $response[0], true)) {
            throw new \RuntimeException('RedisGraph module not loaded.');
        }
    }

    public function execute($command)
    {
        return $this->redisCommand('GRAPH.QUERY', $this->name, $command);
    }

    public function executeAndGet($command): array
    {
        $result = $this->execute($command);
        assert(count($result) === 3, 'count($result) === 3');
        [$header, $data, $statistics] = $result;

        $transformedData = [];
        foreach ($data as $row) {
            $transformedRow = [];
            foreach ($row as $i => $value) {
                $transformedRow[$header[$i]] = CypherConversion::decodeStrings($value);
            }
            $transformedData[] = $transformedRow;
        }
        return $transformedData;
    }

    public function explain($query)
    {
        return $this->redisCommand('GRAPH.EXPLAIN', $this->name, $query);
    }

    public function delete()
    {
        return $this->redisCommand('GRAPH.DELETE', $this->name);
    }

    private function redisCommand()
    {
        switch ($this->client) {
            case self::CLIENT_REDIS:
                return call_user_func_array(
                    [$this->redis, 'rawCommand'],
                    func_get_args()
                );
            case self::CLIENT_PREDIS:
                return $this->redis->executeRaw(func_get_args());
            default:
                throw new \RuntimeException('Unknown Redis client.');
        }
    }
}
