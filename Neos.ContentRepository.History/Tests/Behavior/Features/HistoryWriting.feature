@fixtures
Feature: Writing of history entries on node aggregate events

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values          | Generalizations      |
      | language   | mul     | mul, de, en, ch | ch->de->mul, en->mul |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:NodeWithReferences':
      properties:
        referenceProperty:
          type: reference
        referencesProperty:
          type: references
    """

  Scenario: Create a root node aggregate
    When the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key                      | Value                                  |
      | contentStreamIdentifier  | "cs-identifier"                        |
      | nodeAggregateIdentifier  | "lady-eleonode-rootford"               |
      | nodeTypeName             | "Neos.ContentRepository:Root"          |
      | initiatingUserIdentifier | "00000000-0000-0000-0000-000000000000" |
    And the history projection is fully up to date
    Then I expect the history for node aggregate "lady-eleonode-rootford" to consist of exactly 1 entries
    And I expect history entry number 0 to be:
      | Key                       | Value                                  |
      | type                      | "CreationHistoryEntry"                 |
      | nodeAggregateIdentifier   | "lady-eleonode-rootford"               |
      | agentIdentifier           | "00000000-0000-0000-0000-000000000000" |
      | nodeTypeName              | "Neos.ContentRepository:Root"          |
      | originDimensionSpacePoint | {}                                     |
      | initialPropertyValues     | {}                                     |
