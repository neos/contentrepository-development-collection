@fixtures @adapters=DoctrineDBAL,Postgres
Feature: Set node properties with different scopes

  As a user of the CR I want to modify node references with different scopes.

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values       | Generalizations |
      | language   | mul     | mul, de, gsw | gsw->de->mul    |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:NodeWithReferences':
      properties:
        unscopedReference:
          type: reference
        unscopedReferences:
          type: references
        nodeScopedReference:
          type: reference
          scope: node
        nodeScopedReferences:
          type: references
          scope: node
        nodeAggregateScopedReference:
          type: reference
          scope: nodeAggregate
        nodeAggregateScopedReferences:
          type: references
          scope: nodeAggregate
        specializationsScopedReference:
          type: reference
          scope: specializations
        specializationsScopedReferences:
          type: references
          scope: specializations
    """
    And I am user identified by "initiating-user-identifier"
    And the command CreateRootWorkspace is executed with payload:
      | Key                        | Value                |
      | workspaceName              | "live"               |
      | workspaceTitle             | "Live"               |
      | workspaceDescription       | "The live workspace" |
      | newContentStreamIdentifier | "cs-identifier"      |
    And I am in content stream "cs-identifier" and dimension space point {"language":"mul"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key                     | Value                         |
      | nodeAggregateIdentifier | "lady-eleonode-rootford"      |
      | nodeTypeName            | "Neos.ContentRepository:Root" |
    And the graph projection is fully up to date
    # We have to add another node since root nodes have no dimension space points and thus cannot be varied
    # Node /document
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateIdentifier | parentNodeAggregateIdentifier | nodeTypeName                                      |
      | source-nodandaise       | lady-eleonode-rootford        | Neos.ContentRepository.Testing:NodeWithReferences |
      | anthony-destinode       | lady-eleonode-rootford        | Neos.ContentRepository.Testing:NodeWithReferences |
    And the command CreateNodeVariant is executed with payload:
      | Key                     | Value              |
      | nodeAggregateIdentifier | "source-nodandaise" |
      | sourceOrigin            | {"language":"mul"} |
      | targetOrigin            | {"language":"de"}  |
    And the graph projection is fully up to date
    And the command CreateNodeVariant is executed with payload:
      | Key                     | Value              |
      | nodeAggregateIdentifier | "source-nodandaise" |
      | sourceOrigin            | {"language":"mul"} |
      | targetOrigin            | {"language":"gsw"} |
    And the graph projection is fully up to date

  Scenario: Set node properties
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                 |
      | contentStreamIdentifier             | "cs-identifier"       |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"   |
      | referenceName                       | "unscopedReference"   |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}    |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"] |
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                 |
      | contentStreamIdentifier             | "cs-identifier"       |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"   |
      | referenceName                       | "unscopedReferences"  |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}    |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"] |
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                 |
      | contentStreamIdentifier             | "cs-identifier"       |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"   |
      | referenceName                       | "nodeScopedReference" |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}    |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"] |
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                  |
      | contentStreamIdentifier             | "cs-identifier"        |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"    |
      | referenceName                       | "nodeScopedReferences" |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}     |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"]  |
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                          |
      | contentStreamIdentifier             | "cs-identifier"                |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"            |
      | referenceName                       | "nodeAggregateScopedReference" |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}             |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"]          |
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                           |
      | contentStreamIdentifier             | "cs-identifier"                 |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"             |
      | referenceName                       | "nodeAggregateScopedReferences" |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}              |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"]           |
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                            |
      | contentStreamIdentifier             | "cs-identifier"                  |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"              |
      | referenceName                       | "specializationsScopedReference" |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}               |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"]            |
    And the command SetNodeReferences is executed with payload:
      | Key                                 | Value                             |
      | contentStreamIdentifier             | "cs-identifier"                   |
      | sourceNodeAggregateIdentifier       | "source-nodandaise"               |
      | referenceName                       | "specializationsScopedReferences" |
      | sourceOriginDimensionSpacePoint     | {"language": "de"}                |
      | destinationNodeAggregateIdentifiers | ["anthony-destinode"]             |
    And the graph projection is fully up to date

    When I am in content stream "cs-identifier" and dimension space point {"language": "mul"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "mul"}
    And I expect this node to have the following references:
      | Key                           | Value                                                       |
      | nodeAggregateScopedReference  | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | nodeAggregateScopedReferences | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |

    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Key                           | Value                                                       |
      | nodeAggregateScopedReference  | ["cs-identifier;source-nodandaise;{\"language\": \"mul\"}"] |
      | nodeAggregateScopedReferences | ["cs-identifier;source-nodandaise;{\"language\": \"mul\"}"] |

    When I am in content stream "cs-identifier" and dimension space point {"language": "de"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "de"}
    And I expect this node to have the following references:
      | Key                             | Value                                                       |
      | unscopedReference               | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | unscopedReferences              | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | nodeScopedReference             | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | nodeScopedReferences            | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | nodeAggregateScopedReference    | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | nodeAggregateScopedReferences   | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | specializationsScopedReference  | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | specializationsScopedReferences | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Key                             | Value                                                       |
      | unscopedReference               | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |
      | unscopedReferences              | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |
      | nodeScopedReference             | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |
      | nodeScopedReferences            | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |
      | nodeAggregateScopedReference    | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |
      | nodeAggregateScopedReferences   | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |
      | specializationsScopedReference  | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |
      | specializationsScopedReferences | ["cs-identifier;source-nodandaise;{\"language\": \"de\"}"] |

    When I am in content stream "cs-identifier" and dimension space point {"language": "gsw"}
    Then I expect node aggregate identifier "source-nodandaise" to lead to node cs-identifier;source-nodandaise;{"language": "gsw"}
    And I expect this node to have the following references:
      | Key                             | Value                                                       |
      | nodeAggregateScopedReference    | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | nodeAggregateScopedReferences   | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | specializationsScopedReference  | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
      | specializationsScopedReferences | ["cs-identifier;anthony-destinode;{\"language\": \"mul\"}"] |
    And I expect node aggregate identifier "anthony-destinode" to lead to node cs-identifier;anthony-destinode;{"language": "mul"}
    And I expect this node to be referenced by:
      | Key                             | Value                                                       |
      | nodeAggregateScopedReference    | ["cs-identifier;source-nodandaise;{\"language\": \"gsw\"}"] |
      | nodeAggregateScopedReferences   | ["cs-identifier;source-nodandaise;{\"language\": \"gsw\"}"] |
      | specializationsScopedReference  | ["cs-identifier;source-nodandaise;{\"language\": \"gsw\"}"] |
      | specializationsScopedReferences | ["cs-identifier;source-nodandaise;{\"language\": \"gsw\"}"] |
