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

$script = '
    -- Parse the property structure from https://oss.redislabs.com/redisgraph/result_structure/#nodes
    -- into a table (propertyName -> propertyValue)
    local parseProperties = function(properties)
        local parsedProperties = {}
        for i, row in pairs(properties) do
            local propertyName = row[1]
            local propertyValue = row[2]
            parsedProperties[propertyName] = propertyValue
        end

        return parsedProperties
    end

    -- Parse node properties
    local parseNodeProperties = function(node)
        assert(node[3][1] == "properties", "node properties assertion, found " .. node[3][1])
        local properties = node[3][2]

        return parseProperties(properties)
    end

    -- Parse edge properties
    local parseEdgeProperties = function(edge)
        assert(edge[5][1] == "properties", "edge properties assertion, found " .. edge[3][1])
        local properties = edge[5][2]

        return parseProperties(properties)
    end

    local getRows = function(queryResult)
        -- Index 1: Table Headers
        -- Index 2: Result Data
        -- Index 3: Statistics
        return queryResult[2]
    end
    local getFirstRow = function(queryResult)
        -- return the first row
        return getRows(queryResult)[1]
    end


    local findChildNodes = nil
    findChildNodes = function(parentNodeAggregateIdentifier, levelsSoFar, maximumLevels, dimensionSpacePointHash, nodeTypeConstraintsQueryPart)
        redis.log(redis.LOG_WARNING, parentNodeAggregateIdentifier)
        if levelsSoFar >= maximumLevels then
            return {}
        end

        local rows = getRows(redis.call("GRAPH.QUERY", "test", "MATCH () -[:HIERARCHY {dimensionSpacePointHash: \'" .. dimensionSpacePointHash .. "\'}]-> (:Node {nodeAggregateIdentifier: \'" .. parentNodeAggregateIdentifier .. "\'}) -[:HIERARCHY {dimensionSpacePointHash: \'" .. dimensionSpacePointHash .. "\'}] -> (node:Node) RETURN node"))
        local result = {}
        for i, row in ipairs(rows) do
            local node = parseNodeProperties(row[1])

            local childNodes = findChildNodes(node["nodeAggregateIdentifier"], levelsSoFar + 1, maximumLevels, dimensionSpacePointHash, nodeTypeConstraintsQueryPart)
            result[i] = {
                node = node,
                childNodes = childNodes
            }
        end

        return result
    end


    -- ARGS
    local nodeTypeConstraintsQueryPart = "true"
    local dimensionSpacePointHash = "d1"
    local entryPointNodeAggregateIdentifiers = {"r"}
    local maximumLevels = 3

    local result = {}
    for i, entryPointNodeAggregateIdentifier in ipairs(entryPointNodeAggregateIdentifiers) do
        local queryResult = redis.call("GRAPH.QUERY", "test", "MATCH () -[:HIERARCHY {dimensionSpacePointHash: \'" .. dimensionSpacePointHash .. "\'}]-> (node:Node {nodeAggregateIdentifier: \'" .. entryPointNodeAggregateIdentifier .. "\'}) WHERE " .. nodeTypeConstraintsQueryPart .. " RETURN node")
        -- [1] is the "node" result (1st RETURN value)

        local row = getFirstRow(queryResult)
        if row then
            local node = parseNodeProperties(row[1])
            local childNodes = findChildNodes(node["nodeAggregateIdentifier"], 1, maximumLevels, dimensionSpacePointHash, nodeTypeConstraintsQueryPart)

            table.insert(result, {
                node = node,
                childNodes = childNodes
            })
        end
    end

    return cjson.encode(result)
';
var_export($r->eval($script, [], 0));
var_dump($r->getLastError());

function visualizeGraph(array $result) {
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
    echo implode("\n", $line);
}

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
