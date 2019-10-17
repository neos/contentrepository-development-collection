@fixtures
Feature: Set properties of an existing node

  As a user of the CR I want to set properties of a given node aggregate

  These are the test cases for setting properties with node aggregate scope

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values      | Generalizations |
      | language   | de      | de, en, gsw | gsw->de         |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document':
      properties:
        title:
          type: string
          scope: nodeAggregate
        description:
          type: string
          scope: node
        name:
          type: string
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                            | Value                                  |
      | workspaceName                  | "live"                                 |
      | workspaceTitle                 | "Live"                                 |
      | workspaceDescription           | "The live workspace"                   |
      | initiatingUserIdentifier       | "00000000-0000-0000-0000-000000000000" |
      | currentContentStreamIdentifier | "cs-identifier"                        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                    |
      | contentStreamIdentifier     | "cs-identifier"                                          |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                 |
      | nodeTypeName                | "Neos.ContentRepository:Root"                            |
      | coveredDimensionSpacePoints | [{"language":"de"},{"language":"gsw"},{"language":"en"}] |
      | initiatingUserIdentifier    | "00000000-0000-0000-0000-000000000000"                   |
      | nodeAggregateClassification | "root"                                                   |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                                                                                                                         |
      | contentStreamIdentifier       | "cs-identifier"                                                                                                                                                               |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                                                                                                                                            |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                                                                                                                                     |
      | originDimensionSpacePoint     | {"language":"de"}                                                                                                                                                             |
      | coveredDimensionSpacePoints   | [{"language":"de"},{"language":"gsw"}]                                                                                                                                        |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                                                                                                                                      |
      | nodeName                      | "document"                                                                                                                                                                    |
      | nodeAggregateClassification   | "regular"                                                                                                                                                                     |
      | initialPropertyValues         | {"title": {"type": "string", "value": "initialTitle"}, "description": {"type": "string", "value": "initialDescription"}, "name": {"type": "string", "value": "initialName"}} |
    And the event NodeSpecializationVariantWasCreated was published with payload:
      | Key                     | Value                |
      | contentStreamIdentifier | "cs-identifier"      |
      | nodeAggregateIdentifier | "nody-mc-nodeface"   |
      | sourceOrigin            | {"language":"de"}    |
      | specializationOrigin    | {"language":"gsw"}   |
      | specializationCoverage  | [{"language":"gsw"}] |
    And the graph projection is fully up to date

  Scenario: Set properties of a node that are flagged in different scopes
    When the command SetNodeProperties is executed with payload:
      | Key                       | Value                                                                                                                                                             |
      | contentStreamIdentifier   | "cs-identifier"                                                                                                                                                   |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                                                                                                                                |
      | originDimensionSpacePoint | {"language":"gsw"}                                                                                                                                                |
      | propertyValues            | {"title": {"type": "string", "value": "newTitle"}, "description": {"type": "string", "value": "newDescription"}, "name": {"type": "string", "value": "newName"}} |
    And the graph projection is fully up to date

    Then I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language":"de"}} to exist in the content graph
    And I expect this node to have the properties:
      | Key         | Value              |
      | title       | newTitle           |
      | description | initialDescription |
      | name        | initialName        |
    And I expect a node with identifier {"contentStreamIdentifier":"cs-identifier", "nodeAggregateIdentifier":"nody-mc-nodeface", "originDimensionSpacePoint": {"language":"gsw"}} to exist in the content graph
    And I expect this node to have the properties:
      | Key         | Value          |
      | title       | newTitle       |
      | description | newDescription |
      | name        | newName        |
