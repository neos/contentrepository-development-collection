@fixtures
Feature: Set properties of an existing node

  As a user of the CR I want to set properties of a given node aggregate

  These are the base test cases for the NodeAggregateCommandHandler to block invalid commands

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
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                        |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {"language":"de"}                         |
      | coveredDimensionSpacePoints   | [{"language":"de"},{"language":"gsw"}]    |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                  |
      | nodeName                      | "document"                                |
      | nodeAggregateClassification   | "regular"                                 |
    And the graph projection is fully up to date

  Scenario: Try to set properties a node aggregate in a non-existing content stream:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                          |
      | contentStreamIdentifier   | "non-existing"                                 |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                             |
      | originDimensionSpacePoint | {"language":"de"}                              |
      | propertyValues            | {"title": {"type": "string", "value": "text"}} |
    Then the last command should have thrown an exception of type "ContentStreamDoesNotExistYet"

  Scenario: Try to set properties a node of a non-existing node aggregate:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                          |
      | contentStreamIdentifier   | "cs-identifier"                                |
      | nodeAggregateIdentifier   | "i-do-not-exist"                               |
      | originDimensionSpacePoint | {"language":"de"}                              |
      | propertyValues            | {"title": {"type": "string", "value": "text"}} |
    Then the last command should have thrown an exception of type "NodeAggregateCurrentlyDoesNotExist"

  Scenario: Try to set properties a node aggregate in a non-existing dimension space point:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                          |
      | contentStreamIdentifier   | "cs-identifier"                                |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                             |
      | originDimensionSpacePoint | {"language":"sjn"}                             |
      | propertyValues            | {"title": {"type": "string", "value": "text"}} |
    Then the last command should have thrown an exception of type "DimensionSpacePointNotFound"

  Scenario: Try to set properties a node aggregate in a dimension space point the given node aggregate does not occupy:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                          |
      | contentStreamIdentifier   | "cs-identifier"                                |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                             |
      | originDimensionSpacePoint | {"language":"en"}                              |
      | propertyValues            | {"title": {"type": "string", "value": "text"}} |
    Then the last command should have thrown an exception of type "DimensionSpacePointIsNotYetOccupied"

  Scenario: Try to set undefined properties of a node aggregate:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                              |
      | contentStreamIdentifier   | "cs-identifier"                                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                 |
      | originDimensionSpacePoint | {"language":"de"}                                  |
      | propertyValues            | {"undefined": {"type": "string", "value": "text"}} |
    Then the last command should have thrown an exception of type "PropertyNameIsUndeclaredInNodeType"

  Scenario: Try to set properties to invalid values:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                   |
      | contentStreamIdentifier   | "cs-identifier"                         |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                      |
      | originDimensionSpacePoint | {"language":"de"}                       |
      | propertyValues            | {"title": {"type": "int", "value": 42}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatch"
