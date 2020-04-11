<?php

$r = new \Redis();
$r->connect('127.0.0.1');

var_dump($r->del('test'));
// "rp" is a synthetic "thing" only needed to have incoming edges to r.
// "r" is root node (incoming edges in all DSP)
//  - n1: (d1, d2)
//    - n3 (d1)
//  - n2: (d1)
//    - n3 (d2)
var_dump($r->rawCommand('GRAPH.QUERY', 'test', "
    CREATE
        (rp:RootNodeParent),

        (r:Node {nodeAggregateIdentifier: 'r'}),
        (rp) -[:HIERARCHY{dimensionSpacePointHash: 'd1'}]-> (r),
        (rp) -[:HIERARCHY{dimensionSpacePointHash: 'd2'}]-> (r),

        (n1:Node {nodeAggregateIdentifier: 'n1'}),
        (r) -[:HIERARCHY{dimensionSpacePointHash: 'd1'}]-> (n1),
        (r) -[:HIERARCHY{dimensionSpacePointHash: 'd2'}]-> (n1),

        (n2:Node {nodeAggregateIdentifier: 'n2'}),
        (r) -[:HIERARCHY{dimensionSpacePointHash: 'd1'}]-> (n2),

        (n3:Node {nodeAggregateIdentifier: 'n3'}),
        (n1) -[:HIERARCHY{dimensionSpacePointHash: 'd1'}]-> (n3),
        (n2) -[:HIERARCHY{dimensionSpacePointHash: 'd2'}]-> (n3)
"));

// Create a new node underneath N2
var_dump($r->rawCommand('GRAPH.QUERY', 'test', "
    CREATE
        (newNode:Node {nodeAggregateIdentifier: 'new1'})
"));
var_dump($r->rawCommand('GRAPH.QUERY', 'test', "
    MATCH () -[:HIERARCHY {dimensionSpacePointHash: 'd1'}]-> (parentNode1:Node {nodeAggregateIdentifier: 'n2'})
    MATCH (newNode:Node {nodeAggregateIdentifier: 'new1'})
    CREATE
        (parentNode1) -[:HIERARCHY{dimensionSpacePointHash: 'd1'}]-> (newNode)
"));
// this does not match anything; thus nothing is created.
var_dump($r->rawCommand('GRAPH.QUERY', 'test', "
    MATCH () -[:HIERARCHY {dimensionSpacePointHash: 'd2'}]-> (parentNode1:Node {nodeAggregateIdentifier: 'n2'})
    MATCH (newNode:Node {nodeAggregateIdentifier: 'new1'})
    CREATE
        (parentNode1) -[:HIERARCHY{dimensionSpacePointHash: 'd2'}]-> (newNode)
"));

// Create a new node underneath N3
var_dump($r->rawCommand('GRAPH.QUERY', 'test', "
    CREATE
        (newNode:Node {nodeAggregateIdentifier: 'new2'})
"));
var_dump($r->rawCommand('GRAPH.QUERY', 'test', "
    MATCH () -[:HIERARCHY {dimensionSpacePointHash: 'd1'}]-> (parentNode1:Node {nodeAggregateIdentifier: 'n3'})
    MATCH (newNode:Node {nodeAggregateIdentifier: 'new2'})
    CREATE
        (parentNode1) -[:HIERARCHY{dimensionSpacePointHash: 'd1'}]-> (newNode)
"));
var_dump($r->rawCommand('GRAPH.QUERY', 'test', "
    MATCH () -[:HIERARCHY {dimensionSpacePointHash: 'd2'}]-> (parentNode1:Node {nodeAggregateIdentifier: 'n3'})
    MATCH (newNode:Node {nodeAggregateIdentifier: 'new2'})
    CREATE
        (parentNode1) -[:HIERARCHY{dimensionSpacePointHash: 'd2'}]-> (newNode)
"));

visualizeGraph($r->rawCommand('GRAPH.QUERY', 'test', 'MATCH (m)-[h]->(n) RETURN m, h, n'));



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
