@fixtures
Feature: Run integrity violation detection regarding sibling sorting

  As a user of the CR I want to know whether there are siblings with ambiguous sorting

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values      | Generalizations |
      | language   | de      | de, gsw, fr | gsw->de         |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document': []
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                        | Value                                  |
      | workspaceName              | "live"                                 |
      | workspaceTitle             | "Live"                                 |
      | workspaceDescription       | "The live workspace"                   |
      | initiatingUserIdentifier   | "00000000-0000-0000-0000-000000000000" |
      | newContentStreamIdentifier | "cs-identifier"                        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                    |
      | contentStreamIdentifier     | "cs-identifier"                                          |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                 |
      | nodeTypeName                | "Neos.ContentRepository:Root"                            |
      | coveredDimensionSpacePoints | [{"language":"de"},{"language":"gsw"},{"language":"fr"}] |
      | initiatingUserIdentifier    | "00000000-0000-0000-0000-000000000000"                   |
      | nodeAggregateClassification | "root"                                                   |
    And the graph projection is fully up to date

  Scenario: Create two siblings and set the sorting to the same value
    When the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                    |
      | contentStreamIdentifier       | "cs-identifier"                                          |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                       |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                |
      | originDimensionSpacePoint     | {"language":"de"}                                        |
      | coveredDimensionSpacePoints   | [{"language":"de"},{"language":"gsw"},{"language":"fr"}] |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                 |
      | nodeAggregateClassification   | "regular"                                                |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                    |
      | contentStreamIdentifier       | "cs-identifier"                                          |
      | nodeAggregateIdentifier       | "noderella-mc-nodeface"                                  |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                |
      | originDimensionSpacePoint     | {"language":"de"}                                        |
      | coveredDimensionSpacePoints   | [{"language":"de"},{"language":"gsw"},{"language":"fr"}] |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                 |
      | nodeAggregateClassification   | "regular"                                                |
    And the graph projection is fully up to date
    And I set the following position:
      | Key                          | Value              |
      | contentStreamIdentifier      | "cs-identifier"    |
      | dimensionSpacePoint          | {"language":"de"}  |
      | childNodeAggregateIdentifier | "nody-mc-nodeface" |
      | newPosition                  | 128                |
    And I set the following position:
      | Key                          | Value                   |
      | contentStreamIdentifier      | "cs-identifier"         |
      | dimensionSpacePoint          | {"language":"de"}       |
      | childNodeAggregateIdentifier | "noderella-mc-nodeface" |
      | newPosition                  | 128                     |
    And I run integrity violation detection
    Then I expect the integrity violation detection result to contain exactly 1 error
    And I expect integrity violation detection result error number 1 to have code 1597910918
