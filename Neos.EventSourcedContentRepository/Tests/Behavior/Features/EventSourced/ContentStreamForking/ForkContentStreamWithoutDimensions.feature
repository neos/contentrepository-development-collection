@fixtures
Feature: ForkContentStream Without Dimensions

  We have only one node underneath the root node: /foo.
  LIVE Content Stream ID: cs-identifier
  We fork the live content stream as ID user-cs-identifier
  and then we commit a modification in the LIVE content stream.
  We then expect the *forked* content stream to contain the *original* value; and the *live* content stream must contain the changed value.

  Background:
    Given I have no content dimensions
    """
    Neos.ContentRepository:Root: {}
    'Neos.ContentRepository.Testing:Content':
      properties:
        text:
          type: string
    """
    And the command CreateRootWorkspace is executed with payload:
      | Key                        | Value           |
      | workspaceName              | "live"          |
      | newContentStreamIdentifier | "cs-identifier" |
    And the graph projection is fully up to date
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                         |
      | contentStreamIdentifier     | "cs-identifier"               |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"      |
      | nodeTypeName                | "Neos.ContentRepository:Root" |
      | coveredDimensionSpacePoints | [{}]                          |
      | initiatingUserIdentifier    | "user-identifier"             |
      | nodeAggregateClassification | "root"                        |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                    |
      | contentStreamIdentifier       | "cs-identifier"                          |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                       |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Content" |
      | originDimensionSpacePoint     | {}                                       |
      | coveredDimensionSpacePoints   | [{}]                                     |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                 |
      | nodeName                      | "child"                                  |
      | nodeAggregateClassification   | "regular"                                |
    And the Event "Neos.EventSourcedContentRepository:NodePropertiesWereSet" was published to stream "Neos.ContentRepository:ContentStream:cs-identifier" with payload:
      | Key                       | Value                                                   |
      | contentStreamIdentifier   | "cs-identifier"                                         |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                      |
      | originDimensionSpacePoint | {}                                                      |
      | propertyValues            | {"text": {"value": "original value", "type": "string"}} |
      | initiatingUserIdentifier      | "initiating-user-identifier" |

  Scenario: Ensure that the node is available in the forked content stream
    When the command "ForkContentStream" is executed with payload:
      | Key                           | Value                |
      | contentStreamIdentifier       | "user-cs-identifier" |
      | sourceContentStreamIdentifier | "cs-identifier"      |
      | initiatingUserIdentifier      | "initiating-user-identifier" |
    And the graph projection is fully up to date
    And I am in content stream "user-cs-identifier" and Dimension Space Point {}

    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph

  Scenario: When a change is applied to the forked content stream AFTER the fork, it is not visible in the live content stream.
    When the command "ForkContentStream" is executed with payload:
      | Key                           | Value                |
      | contentStreamIdentifier       | "user-cs-identifier" |
      | sourceContentStreamIdentifier | "cs-identifier"      |
      | initiatingUserIdentifier      | "initiating-user-identifier" |
    And the Event "Neos.EventSourcedContentRepository:NodePropertiesWereSet" was published to stream "Neos.ContentRepository:ContentStream:user-cs-identifier" with payload:
      | Key                       | Value                                                   |
      | contentStreamIdentifier   | "user-cs-identifier"                                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                      |
      | originDimensionSpacePoint | {}                                                      |
      | propertyValues            | {"text": {"value": "modified value", "type": "string"}} |
      | initiatingUserIdentifier      | "initiating-user-identifier" |
    And the graph projection is fully up to date

      # live
    When I am in content stream "cs-identifier" and Dimension Space Point {}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key  | Value          |
      | text | original value |

    # forked content stream
    When I am in content stream "user-cs-identifier" and Dimension Space Point {}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key  | Value          |
      | text | modified value |

  # this is a "reverse" scenario of the scenario above.
  Scenario: When a change is applied on the live content stream AFTER the fork, it is NOT visible in the forked content stream.
    When the command "ForkContentStream" is executed with payload:
      | Key                           | Value                |
      | contentStreamIdentifier       | "user-cs-identifier" |
      | sourceContentStreamIdentifier | "cs-identifier"      |
      | initiatingUserIdentifier      | "initiating-user-identifier" |
    And the Event "Neos.EventSourcedContentRepository:NodePropertiesWereSet" was published to stream "Neos.ContentRepository:ContentStream:cs-identifier" with payload:
      | Key                       | Value                                                   |
      | contentStreamIdentifier   | "cs-identifier"                                         |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                      |
      | originDimensionSpacePoint | {}                                                      |
      | propertyValues            | {"text": {"value": "modified value", "type": "string"}} |
      | initiatingUserIdentifier      | "initiating-user-identifier" |
    And the graph projection is fully up to date

    # live
    When I am in content stream "cs-identifier" and Dimension Space Point {}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key  | Value          |
      | text | modified value |

    # forked content stream
    When I am in content stream "user-cs-identifier" and Dimension Space Point {}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key  | Value          |
      | text | original value |
