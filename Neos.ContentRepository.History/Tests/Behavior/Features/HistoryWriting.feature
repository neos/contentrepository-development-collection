@fixtures
Feature: Writing of history entries on node aggregate events

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values          | Generalizations      |
      | language   | mul     | mul, de, en, ch | ch->de->mul, en->mul |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document':
      properties:
        text:
          type: string
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                            | Value                                  |
      | workspaceName                  | "live"                                 |
      | workspaceTitle                 | "Live"                                 |
      | workspaceDescription           | "The live workspace"                   |
      | initiatingUserIdentifier       | "00000000-0000-0000-0000-000000000000" |
      | currentContentStreamIdentifier | "cs-identifier"                        |

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

  Scenario: Create a node aggregate
    When the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key                      | Value                                  |
      | contentStreamIdentifier  | "cs-identifier"                        |
      | nodeAggregateIdentifier  | "lady-eleonode-rootford"               |
      | nodeTypeName             | "Neos.ContentRepository:Root"          |
      | initiatingUserIdentifier | "00000000-0000-0000-0000-000000000000" |
    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                           | Value                                     |
      | contentStreamIdentifier       | "cs-identifier"                           |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                        |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | originDimensionSpacePoint     | {}                                        |
      | initiatingUserIdentifier      | "00000000-0000-0000-0000-000000000000"    |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                  |
      | nodeName                      | "nody-mc-nodeface"                        |
      | initialPropertyValues         | {"text": "test"}                          |
    And the history projection is fully up to date
    Then I expect the history for node aggregate "nody-mc-nodeface" to consist of exactly 1 entries
    And I expect history entry number 0 to be:
      | Key                           | Value                                     |
      | type                          | "CreationHistoryEntry"                    |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                        |
      | agentIdentifier               | "00000000-0000-0000-0000-000000000000"    |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document" |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                  |
      | nodeName                      | "nody-mc-nodeface"                        |
      | originDimensionSpacePoint     | {}                                        |
      | initialPropertyValues         | {"text": "test"}                          |
