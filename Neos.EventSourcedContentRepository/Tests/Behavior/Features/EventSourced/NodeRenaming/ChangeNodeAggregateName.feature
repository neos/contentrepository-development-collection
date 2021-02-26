@fixtures
Feature: Change node name

  As a user of the CR I want to change the name of a hierarchical relation between two nodes (e.g. in taxonomies)

  Background:
    Given I have no content dimensions
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                         |
      | contentStreamIdentifier     | "cs-identifier"               |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"      |
      | nodeTypeName                | "Neos.ContentRepository:Root" |
      | coveredDimensionSpacePoints | [{}]                          |
      | initiatingUserIdentifier    | "system"                      |
      | nodeAggregateClassification | "root"                        |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository.Testing:Content': []
    """

  Scenario: Change node name of content node
    Given the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                    |
      | contentStreamIdentifier       | "cs-identifier"                          |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                       |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Content" |
      | originDimensionSpacePoint     | {}                                       |
      | coveredDimensionSpacePoints   | [{}]                                     |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                 |
      | nodeName                      | "dog"                                    |
      | nodeAggregateClassification   | "regular"                                |

    And the graph projection is fully up to date
    When the command "ChangeNodeAggregateName" is executed with payload:
      | Key                     | Value              |
      | contentStreamIdentifier | "cs-identifier"    |
      | nodeAggregateIdentifier | "nody-mc-nodeface" |
      | newNodeName             | "cat"              |
      | initiatingUserIdentifier      | "initiating-user-identifier" |

    Then I expect exactly 3 events to be published on stream with prefix "Neos.ContentRepository:ContentStream:cs-identifier"
    And event at index 2 is of type "Neos.EventSourcedContentRepository:NodeAggregateNameWasChanged" with payload:
      | Key                     | Expected           |
      | contentStreamIdentifier | "cs-identifier"    |
      | nodeAggregateIdentifier | "nody-mc-nodeface" |
      | newNodeName             | "cat"              |

  # @todo reenable once this is properly implemented
  #Scenario: Change node name actually updates projection
  #  Given the event NodeAggregateWithNodeWasCreated was published with payload:
  #    | Key                           | Value                                    |
  #    | contentStreamIdentifier       | "cs-identifier"                          |
  #    | nodeAggregateIdentifier       | "nody-mc-nodeface"                       |
   ##   | nodeTypeName                  | "Neos.ContentRepository.Testing:Content" |
   #   | originDimensionSpacePoint     | {}                                       |
   #   | coveredDimensionSpacePoints | [{}]                                     |
   #   | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                 |
   #   | nodeName                      | "dog"                                  |
   # And the graph projection is fully up to date
   # When the command "ChangeNodeAggregateName" is executed with payload:
   #   | Key                     | Value              |
   #   | contentStreamIdentifier | "cs-identifier"    |
   #   | nodeAggregateIdentifier | "nody-mc-nodeface" |
   #   | newNodeName             | "cat"              |
   # And the graph projection is fully up to date

    #When I am in content stream "cs-identifier" and Dimension Space Point {}
    #Then I expect the node aggregate "lady-eleonode-rootford" to have the following child nodes:
    #  | Name | NodeAggregateIdentifier |
    #  | cat  | nody-mc-nodeface        |
