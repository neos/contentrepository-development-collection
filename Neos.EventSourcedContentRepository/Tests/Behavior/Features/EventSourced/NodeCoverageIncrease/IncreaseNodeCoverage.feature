@fixtures
Feature: Increase node aggregate coverage

  As a user of the CR I want to increase the dimension space coverage
  of a given node aggregate
  in a given content stream
  using a given origin dimension space point
  by a given dimension space point set.

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values           | Generalizations       |
      | language   | mul     | mul, de, en, gsw | gsw->de->mul, en->mul |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document':
      childNodes:
        tethered:
          type: 'Neos.ContentRepository.Testing:Tethered'
    'Neos.ContentRepository.Testing:Tethered': []
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                        | Value                        |
      | workspaceName              | "live"                       |
      | workspaceTitle             | "Live"                       |
      | workspaceDescription       | "The live workspace"         |
      | newContentStreamIdentifier | "cs-identifier"              |
      | initiatingUserIdentifier   | "initiating-user-identifier" |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                                       |
      | contentStreamIdentifier     | "cs-identifier"                                                             |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                                    |
      | nodeTypeName                | "Neos.ContentRepository:Root"                                               |
      | coveredDimensionSpacePoints | [{"language":"mul"},{"language":"de"},{"language":"en"},{"language":"gsw"}] |
      | nodeAggregateClassification | "root"                                                                      |
      | initiatingUserIdentifier    | "initiating-user-identifier"                                                |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                    |
      | contentStreamIdentifier       | "cs-identifier"                                          |
      | nodeAggregateIdentifier       | "sir-david-nodenborough"                                 |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                |
      | originDimensionSpacePoint     | {"language":"mul"}                                       |
      | coveredDimensionSpacePoints   | [{"language":"mul"},{"language":"en"},{"language":"de"}] |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                 |
      | nodeName                      | "document"                                               |
      | nodeAggregateClassification   | "regular"                                                |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                    |
      | contentStreamIdentifier       | "cs-identifier"                                          |
      | nodeAggregateIdentifier       | "nodewyn-tetherton"                                      |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Tethered"                |
      | originDimensionSpacePoint     | {"language":"mul"}                                       |
      | coveredDimensionSpacePoints   | [{"language":"mul"},{"language":"en"},{"language":"de"}] |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"                                 |
      | nodeName                      | "tethered"                                               |
      | nodeAggregateClassification   | "tethered"                                               |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                    |
      | contentStreamIdentifier       | "cs-identifier"                                          |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                       |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                |
      | originDimensionSpacePoint     | {"language":"mul"}                                       |
      | coveredDimensionSpacePoints   | [{"language":"mul"},{"language":"en"},{"language":"de"}] |
      | parentNodeAggregateIdentifier | "nodewyn-tetherton"                                      |
      | nodeName                      | "child-document"                                               |
      | nodeAggregateClassification   | "regular"                                                |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                    |
      | contentStreamIdentifier       | "cs-identifier"                                          |
      | nodeAggregateIdentifier       | "nodimer-tetherton"                                      |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Tethered"                |
      | originDimensionSpacePoint     | {"language":"mul"}                                       |
      | coveredDimensionSpacePoints   | [{"language":"mul"},{"language":"en"},{"language":"de"}] |
      | parentNodeAggregateIdentifier | "nody-mc-nodeface"                                       |
      | nodeName                      | "child-tethered"                                               |
      | nodeAggregateClassification   | "tethered"                                               |
    And the graph projection is fully up to date

  Scenario: Increase node aggregate coverage
    When the command IncreaseNodeAggregateCoverage is executed with payload:
      | Key                       | Value                        |
      | contentStreamIdentifier   | "cs-identifier"              |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"     |
      | originDimensionSpacePoint | {"language": "mul"}          |
      | additionalCoverage        | [{"language": "gsw"}]        |
      | initiatingUserIdentifier  | "initiating-user-identifier" |
    # 1 for the root workspace, 5 for the initial nodes, 1 for sir-david-nodenborough and 1 for nodewyn-tetherton
    Then I expect exactly 8 events to be published on stream "Neos.ContentRepository:ContentStream:cs-identifier"
    And event at index 6 is of type "Neos.EventSourcedContentRepository:NodeAggregateCoverageWasIncreased" with payload:
      | Key                       | Expected                     |
      | contentStreamIdentifier   | "cs-identifier"              |
      | nodeAggregateIdentifier   | "sir-david-nodenborough"     |
      | originDimensionSpacePoint | {"language": "mul"}          |
      | additionalCoverage        | [{"language": "gsw"}]        |
      | initiatingUserIdentifier  | "initiating-user-identifier" |
    And event at index 7 is of type "Neos.EventSourcedContentRepository:NodeAggregateCoverageWasIncreased" with payload:
      | Key                       | Expected                     |
      | contentStreamIdentifier   | "cs-identifier"              |
      | nodeAggregateIdentifier   | "nodewyn-tetherton"          |
      | originDimensionSpacePoint | {"language": "mul"}          |
      | additionalCoverage        | [{"language": "gsw"}]        |
      | initiatingUserIdentifier  | "initiating-user-identifier" |

    When the graph projection is fully up to date
    Then I expect the graph projection to consist of exactly 5 nodes
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language": "mul"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language": "mul"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language": "mul"}} to exist in the content graph
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimer-tetherton", "originDimensionSpacePoint": {"language": "mul"}} to exist in the content graph

    When I am in content stream "cs-identifier"
    Then I expect the node aggregate "lady-eleonode-rootford" to exist
    And I expect this node aggregate to occupy dimension space points [{}]
    And I expect this node aggregate to cover dimension space points [{"language":"mul"},{"language":"de"},{"language":"en"},{"language":"gsw"}]

    And I expect the node aggregate "sir-david-nodenborough" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"mul"}]
    And I expect this node aggregate to cover dimension space points [{"language":"mul"},{"language":"de"},{"language":"en"},{"language":"gsw"}]

    And I expect the node aggregate "nodewyn-tetherton" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"mul"}]
    And I expect this node aggregate to cover dimension space points [{"language":"mul"},{"language":"de"},{"language":"en"},{"language":"gsw"}]

    And I expect the node aggregate "nody-mc-nodeface" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"mul"}]
    And I expect this node aggregate to cover dimension space points [{"language":"mul"},{"language":"de"},{"language":"en"}]

    And I expect the node aggregate "nodimer-tetherton" to exist
    And I expect this node aggregate to occupy dimension space points [{"language":"mul"}]
    And I expect this node aggregate to cover dimension space points [{"language":"mul"},{"language":"de"},{"language":"en"}]

    When I am in content stream "cs-identifier" and Dimension Space Point {"language":"mul"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nodewyn-tetherton" and path "document/tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/tethered/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nodimer-tetherton" and path "document/tethered/child-document/child-tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimer-tetherton", "originDimensionSpacePoint": {"language":"mul"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"language":"en"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nodewyn-tetherton" and path "document/tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/tethered/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nodimer-tetherton" and path "document/tethered/child-document/child-tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimer-tetherton", "originDimensionSpacePoint": {"language":"mul"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"language":"de"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nodewyn-tetherton" and path "document/tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/tethered/child-document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nodimer-tetherton" and path "document/tethered/child-document/child-tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodimer-tetherton", "originDimensionSpacePoint": {"language":"mul"}}

    When I am in content stream "cs-identifier" and Dimension Space Point {"language":"gsw"}
    Then I expect node aggregate identifier "lady-eleonode-rootford" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"lady-eleonode-rootford", "originDimensionSpacePoint": {}}
    And I expect node aggregate identifier "sir-david-nodenborough" and path "document" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"sir-david-nodenborough", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nodewyn-tetherton" and path "document/tethered" to lead to node {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nodewyn-tetherton", "originDimensionSpacePoint": {"language":"mul"}}
    And I expect node aggregate identifier "nody-mc-nodeface" and path "document/tethered/child-document" to lead to no node
    And I expect node aggregate identifier "nodimer-tetherton" and path "document/tethered/child-document/child-tethered" to lead to no node
