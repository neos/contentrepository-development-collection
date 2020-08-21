@fixtures
Feature: Set properties of a node

  As a user of the CR I want to set properties of a node

  These are the test cases for the different property scopes

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values               | Generalizations          |
      | locale     | en      | en, de, gsw, ltz, fr | gsw->de->en, ltz->de->en |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document':
      properties:
        unscoped:
          type: string
        nodeScoped:
          type: string
          scope: node
        specializationsScoped:
          type: string
          scope: specializations
        nodeAggregateScoped:
          type: string
          scope: nodeAggregate
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                        | Value                                  |
      | workspaceName              | "live"                                 |
      | workspaceTitle             | "Live"                                 |
      | workspaceDescription       | "The live workspace"                   |
      | initiatingUserIdentifier   | "00000000-0000-0000-0000-000000000000" |
      | newContentStreamIdentifier | "cs-identifier"                        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                              |
      | contentStreamIdentifier     | "cs-identifier"                                                    |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                           |
      | nodeTypeName                | "Neos.ContentRepository:Root"                                      |
      | coveredDimensionSpacePoints | [{"locale":"en"},{"locale":"de"},{"locale":"gsw"},{"locale":"fr"}] |
      | initiatingUserIdentifier    | "00000000-0000-0000-0000-000000000000"                             |
      | nodeAggregateClassification | "root"                                                             |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                              |
      | contentStreamIdentifier       | "cs-identifier"                                                    |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                                 |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                          |
      | originDimensionSpacePoint     | {"locale":"en"}                                                    |
      | coveredDimensionSpacePoints   | [{"locale":"en"},{"locale":"de"},{"locale":"gsw"},{"locale":"fr"}] |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                           |
      | nodeName                      | "document"                                                         |
      | nodeAggregateClassification   | "regular"                                                          |
    And the event NodeSpecializationVariantWasCreated was published with payload:
      | Key                     | Value                              |
      | contentStreamIdentifier | "cs-identifier"                    |
      | nodeAggregateIdentifier | "nody-mc-nodeface"                 |
      | sourceOrigin            | {"locale":"en"}                    |
      | specializationOrigin    | {"locale":"de"}                    |
      | specializationCoverage  | [{"locale":"de"},{"locale":"gsw"}] |
    And the event NodeSpecializationVariantWasCreated was published with payload:
      | Key                     | Value              |
      | contentStreamIdentifier | "cs-identifier"    |
      | nodeAggregateIdentifier | "nody-mc-nodeface" |
      | sourceOrigin            | {"locale":"en"}    |
      | specializationOrigin    | {"locale":"gsw"}   |
      | specializationCoverage  | [{"locale":"gsw"}] |
    And the event NodePeerVariantWasCreated was published with payload:
      | Key                     | Value              |
      | contentStreamIdentifier | "cs-identifier"    |
      | nodeAggregateIdentifier | "nody-mc-nodeface" |
      | sourceOrigin            | {"locale":"en"}    |
      | peerOrigin              | {"locale":"fr"}    |
      | peerCoverage            | [{"locale":"fr"}]  |
    And the graph projection is fully up to date

  Scenario: Set differently scoped node properties
    When the command SetNodeProperties is executed with payload:
      | Key                       | Value                                                                                                                                              |
      | contentStreamIdentifier   | "cs-identifier"                                                                                                                                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                                                                                                                 |
      | originDimensionSpacePoint | {"locale":"de"}                                                                                                                                    |
      | propertyValues            | {"unscoped":"Nody McNodeface", "nodeScoped":"Nody McNodeface", "specializationsScoped":"Nody McNodeface", "nodeAggregateScoped":"Nody McNodeface"} |
    And the graph projection is fully up to date
    When I am in content stream "cs-identifier" and Dimension Space Point {"locale": "en"}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key                 | Type   | Value             |
      | nodeAggregateScoped | string | "Nody McNodeface" |
    When I am in content stream "cs-identifier" and Dimension Space Point {"locale": "de"}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key                   | Type   | Value             |
      | unscoped              | string | "Nody McNodeface" |
      | nodeScoped            | string | "Nody McNodeface" |
      | specializationsScoped | string | "Nody McNodeface" |
      | nodeAggregateScoped   | string | "Nody McNodeface" |
    When I am in content stream "cs-identifier" and Dimension Space Point {"locale": "gsw"}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key                   | Type   | Value             |
      | specializationsScoped | string | "Nody McNodeface" |
      | nodeAggregateScoped   | string | "Nody McNodeface" |
    When I am in content stream "cs-identifier" and Dimension Space Point {"locale": "fr"}
    Then I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key                 | Type   | Value             |
      | nodeAggregateScoped | string | "Nody McNodeface" |

