<?php


namespace Neos\ContentGraph\RedisGraphAdapter\Redis\Graph;


use Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\Dumper\Edge;
use Neos\ContentGraph\RedisGraphAdapter\Redis\Graph\Dumper\Node;

class GraphDumper
{

    public static function render(Graph $graph): string
    {
        $result = $graph->execute('MATCH (m)-[h]->(n) RETURN m, h, n');

        [$header, $data, $statistics] = $result;

        assert($header[0] === 'm', 'header[0] === m');
        assert($header[1] === 'h', 'header[1] === h');
        assert($header[2] === 'n', 'header[2] === n');

        $line = [];
        foreach ($data as $row) {
            [$m, $h, $n] = $row;
            $line[] = Node::fromResult($m) . Edge::fromResult($h) . Node::fromResult($n) . "\n";
        }

        // we sort alphabetically because it looks nicer and more stable
        asort($line);
        return implode("\n", $line);
    }
}
