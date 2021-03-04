@fixtures
Feature: Increase node aggregate coverage

  As a user of the CR I want to increase the dimension space coverage
  of a given node aggregate
  in a given content stream
  using a given origin dimension space point
  by a given dimension space point set
  *with recursion*

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values  | Generalizations |
      | language   | de      | de, ltz | ltz->de         |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Node': []
    'Neos.ContentRepository.Testing:NodeWithTetheredChild':
      childNodes:
        tethered:
          type: 'Neos.ContentRepository.Testing:Tethered'
    'Neos.ContentRepository.Testing:Tethered': []
    """
    And the command CreateRootWorkspace is executed with payload:
      | Key                        | Value                        |
      | workspaceName              | "live"                       |
      | workspaceTitle             | "Live"                       |
      | workspaceDescription       | "The live workspace"         |
      | initiatingUserIdentifier   | "initiating-user-identifier" |
      | newContentStreamIdentifier | "cs-identifier"              |
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key                      | Value                         |
      | contentStreamIdentifier  | "cs-identifier"               |
      | nodeAggregateIdentifier  | "lady-eleonode-rootford"      |
      | nodeTypeName             | "Neos.ContentRepository:Root" |
      | initiatingUserIdentifier | "initiating-user-identifier"  |
    And the graph projection is fully up to date
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                                        | Value                                                  |
      | contentStreamIdentifier                    | "cs-identifier"                                        |
      | nodeAggregateIdentifier                    | "sir-david-nodenborough"                               |
      | nodeTypeName                               | "Neos.ContentRepository.Testing:NodeWithTetheredChild" |
      | originDimensionSpacePoint                  | {"language":"de"}                                      |
      | initiatingUserIdentifier                   | "initiating-user-identifier"                           |
      | parentNodeAggregateIdentifier              | "lady-eleonode-rootford"                               |
      | nodeName                                   | "node"                                                 |
      | tetheredDescendantNodeAggregateIdentifiers | {"tethered": "nodewyn-tetherton"}                      |
    And the graph projection is fully up to date
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                           | Value                                 |
      | contentStreamIdentifier       | "cs-identifier"                       |
      | nodeAggregateIdentifier       | "sir-nodeward-nodington-iii"          |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Node" |
      | originDimensionSpacePoint     | {"language":"de"}                     |
      | initiatingUserIdentifier      | "initiating-user-identifier"          |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"              |
      | nodeName                      | "esquire"                             |
    And the graph projection is fully up to date
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                           | Value                                 |
      | contentStreamIdentifier       | "cs-identifier"                       |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                    |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Node" |
      | originDimensionSpacePoint     | {"language":"de"}                     |
      | initiatingUserIdentifier      | "initiating-user-identifier"          |
      | parentNodeAggregateIdentifier | "sir-nodeward-nodington-iii"          |
      | nodeName                      | "child-node"                          |
    And the graph projection is fully up to date
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                           | Value                                 |
      | contentStreamIdentifier       | "cs-identifier"                       |
      | nodeAggregateIdentifier       | "nodingers-cat"                       |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Node" |
      | originDimensionSpacePoint     | {"language":"de"}                     |
      | initiatingUserIdentifier      | "initiating-user-identifier"          |
      | parentNodeAggregateIdentifier | "nody-mc-nodeface"                    |
      | nodeName                      | "pet-node"                            |
    And the graph projection is fully up to date
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                           | Value                                 |
      | contentStreamIdentifier       | "cs-identifier"                       |
      | nodeAggregateIdentifier       | "anthony-destinode"                   |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Node" |
      | originDimensionSpacePoint     | {"language":"de"}                     |
      | initiatingUserIdentifier      | "initiating-user-identifier"          |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"              |
      | nodeName                      | "destination"                         |
    And the graph projection is fully up to date
    And the command MoveNodeAggregate is executed with payload:
      | Key                                         | Value               |
      | contentStreamIdentifier                     | "cs-identifier"     |
      | nodeAggregateIdentifier                     | "nody-mc-nodeface"  |
      | dimensionSpacePoint                         | {"language":"ltz"}  |
      | newParentNodeAggregateIdentifier            | "anthony-destinode" |
      | newSucceedingSiblingNodeAggregateIdentifier | null                |
      | relationDistributionStrategy                | "scatter"           |
    And the graph projection is fully up to date
    And the command RemoveNodeAggregate is executed with payload:
      | Key                          | Value                    |
      | contentStreamIdentifier      | "cs-identifier"          |
      | nodeAggregateIdentifier      | "sir-david-nodenborough" |
      | nodeVariantSelectionStrategy | "allSpecializations"     |
      | coveredDimensionSpacePoint   | {"language":"ltz"}       |
    And the graph projection is fully up to date
    And the command RemoveNodeAggregate is executed with payload:
      | Key                          | Value                |
      | contentStreamIdentifier      | "cs-identifier"      |
      | nodeAggregateIdentifier      | "nodingers-cat"      |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | coveredDimensionSpacePoint   | {"language":"ltz"}   |
    And the graph projection is fully up to date

  Scenario: Increase node aggregate coverage
    When the command IncreaseNodeAggregateCoverage is executed with payload:
      | Key                       | Value                        |
      | contentStreamIdentifier   | "cs-identifier"              |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"     |
      | originDimensionSpacePoint | {"language": "de"}           |
      | additionalCoverage        | [{"language": "ltz"}]        |
      | initiatingUserIdentifier  | "initiating-user-identifier" |
      | recursive                 | true                         |
    # 1 for the root workspace, 7 for the initial nodes, 1 for moving, 2 for deletion, 1 for coverage
    Then I expect exactly 12 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier"
    And event at index 11 is of type "Neos.EventSourcedContentRepository:NodeAggregateCoverageWasIncreased" with payload:
      | Key                       | Expected                     |
      | contentStreamIdentifier   | "cs-identifier"              |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"     |
      | originDimensionSpacePoint | {"language": "de"}           |
      | additionalCoverage        | [{"language": "ltz"}]        |
      | initiatingUserIdentifier  | "initiating-user-identifier" |
      | recursive                 | true                         |

    When the graph projection is fully up to date
    Then I expect the graph projection to consist of exactly 7 nodes
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language": "de"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language": "de"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-nodeward-nodington-iii", "originDimensionSpacePoint": {"language": "de"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language": "de"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodingers-cat", "originDimensionSpacePoint": {"language": "de"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"anthony-destinode", "originDimensionSpacePoint": {"language": "de"}} to exist in the content graph

    When I am in content stream "cs-identifier"
    Then I expect the node aggregate "lady-eleonode-rootford" to exist
    And I expect this node aggregate to occupy dimension space points [{}]
    And I expect this node aggregate to cover dimension space points [{"language":"de"},{"language":"ltz"}]

    And I expect the node aggregate "sir-david-nodenborough" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"de"}]
    And I expect this node aggregate to cover dimension space points [{"language":"de"},{"language":"ltz"}]

    And I expect the node aggregate "nodewyn-tetherton" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"de"}]
    And I expect this node aggregate to cover dimension space points [{"language":"de"},{"language":"ltz"}]

    And I expect the node aggregate "sir-nodeward-nodington-iii" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"de"}]
    And I expect this node aggregate to cover dimension space points [{"language":"de"},{"language":"ltz"}]

    And I expect the node aggregate "nody-mc-nodeface" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"de"}]
    And I expect this node aggregate to cover dimension space points [{"language":"de"},{"language":"ltz"}]

    And I expect the node aggregate "nodingers-cat" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"de"}]
    And I expect this node aggregate to cover dimension space points [{"language":"de"}]

    And I expect the node aggregate "anthony-destinode" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"de"}]
    And I expect this node aggregate to cover dimension space points [{"language":"de"},{"language":"ltz"}]

    When I am in content stream "cs-identifier" and Dimension Space Point {"language":"de"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "node" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "nodewyn-tetherton" and path "node/tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "sir-nodeward-nodington-iii" and path "node/esquire" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-nodeward-nodington-iii", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "node/esquire/child-node" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "nodingers-cat" and path "node/esquire/child-node/pet-node" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodingers-cat", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "anthony-destinode" and path "destination" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"anthony-destinode", "originDimensionSpacePoint": {"language":"de"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"language":"ltz"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "node" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "nodewyn-tetherton" and path "node/tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "sir-nodeward-nodington-iii" and path "node/esquire" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-nodeward-nodington-iii", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "anthony-destinode" and path "destination" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"anthony-destinode", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "destination/child-node" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language":"de"}}
    And I expect node aggregate identifier "nodingers-cat" and path "destination/child-node/pet-node" to lead to no node
