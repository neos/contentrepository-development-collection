@fixtures
# Note: For the routing tests to work we rely on Configuration/Testing/Behat/NodeTypes.Test.Routing.yaml
Feature: Routing functionality with multiple content dimensions

  Background:
    Given I have the following content dimension configuration:
      """
      market:
        defaultValue: DE
        values:
          DE:
           resolution:
             value: ''
           specializations:
             CH:
               resolution:
                 value: ''
      language:
        defaultValue: en
        values:
          en:
            resolution:
              value: ''
            specializations:
              de:
                resolution:
                  value: 'de'
              gsw:
                resolution:
                  value: 'gsw'
      """
    And the command CreateRootWorkspace is executed with payload:
      | Key                        | Value           |
      | workspaceName              | "live"          |
      | newContentStreamIdentifier | "cs-identifier" |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                                                                                                                                                                     |
      | contentStreamIdentifier     | "cs-identifier"                                                                                                                                                                                           |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                                                                                                                                                                  |
      | nodeTypeName                | "Neos.Neos:Sites"                                                                                                                                                                                         |
      | coveredDimensionSpacePoints | [{"market":"DE", "language":"en"},{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"en"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
      | initiatingUserIdentifier    | "system-user"                                                                                                                                                                                             |
      | nodeAggregateClassification | "root"                                                                                                                                                                                                    |
    And the graph projection is fully up to date
    # NOTE: The "nodeName" column only exists because it's currently not possible to create unnamed nodes (see https://github.com/neos/contentrepository-development-collection/pull/162)
    And the following CreateNodeAggregateWithNode commands are executed for content stream "cs-identifier" and origin '{"market":"DE", "language":"en"}':
      | nodeAggregateIdentifier | parentNodeAggregateIdentifier | nodeTypeName                                       | initialPropertyValues           | nodeName |
      | sir-david-nodenborough  | lady-eleonode-rootford        | Neos.EventSourcedNeosAdjustments:Test.Routing.Page | {"uriPathSegment": "ignore-me"} | node1    |
      | nody-mc-nodeface        | sir-david-nodenborough        | Neos.EventSourcedNeosAdjustments:Test.Routing.Page | {"uriPathSegment": "nody"}      | node2    |
      | carl-destinode          | nody-mc-nodeface              | Neos.EventSourcedNeosAdjustments:Test.Routing.Page | {"uriPathSegment": "carl"}      | node3    |
    And the command CreateNodeVariant is executed with payload:
      | Key                     | Value                            |
      | contentStreamIdentifier | "cs-identifier"                  |
      | nodeAggregateIdentifier | "carl-destinode"                 |
      | sourceOrigin            | {"market":"DE", "language":"en"} |
      | targetOrigin            | {"market":"DE", "language":"de"} |
    And the graph projection is fully up to date
    And the command "SetNodeProperties" is executed with payload:
      | Key                       | Value                            |
      | contentStreamIdentifier   | "cs-identifier"                  |
      | nodeAggregateIdentifier   | "carl-destinode"                 |
      | originDimensionSpacePoint | {"market":"DE", "language":"de"} |
      | propertyValues            | {"uriPathSegment": "karl-de"}    |
    And A site exists for node name "node1"
    And the graph projection is fully up to date
    And The documenturipath projection is up to date

  Scenario: Resolve homepage URL in multiple dimensions
    When I am on URL "/"
    Then the node "sir-david-nodenborough" in content stream "cs-identifier" and dimension '{"market":"CH", "language":"en"}' should resolve to URL "/"
    And the node "sir-david-nodenborough" in content stream "cs-identifier" and dimension '{"market":"CH", "language":"de"}' should resolve to URL "/de/"
    And the node "sir-david-nodenborough" in content stream "cs-identifier" and dimension '{"market":"DE", "language":"de"}' should resolve to URL "/de/"

  Scenario: Resolve node URLs in multiple dimensions
    When I am on URL "/"
    Then the node "carl-destinode" in content stream "cs-identifier" and dimension '{"market":"CH", "language":"en"}' should resolve to URL "/nody/carl"
    And the node "carl-destinode" in content stream "cs-identifier" and dimension '{"market":"CH", "language":"de"}' should resolve to URL "/de/nody/carl"
    And the node "carl-destinode" in content stream "cs-identifier" and dimension '{"market":"DE", "language":"de"}' should resolve to URL "/de/nody/karl-de"

  Scenario: Match homepage node in default dimension
    When I am on URL "/"
    Then the matched node should be "sir-david-nodenborough" in content stream "cs-identifier" and dimension '{"market":"DE", "language":"en"}'

  Scenario: Match homepage node in specific dimension
    When I am on URL "/de"
    Then the matched node should be "sir-david-nodenborough" in content stream "cs-identifier" and dimension '{"market":"DE", "language":"de"}'

  Scenario: Match node in default dimension
    When I am on URL "/nody/carl"
    Then the matched node should be "carl-destinode" in content stream "cs-identifier" and dimension '{"market":"DE", "language":"en"}'

  Scenario: Match node in specific dimension
    When I am on URL "/de/nody/karl-de"
    Then the matched node should be "carl-destinode" in content stream "cs-identifier" and dimension '{"market":"DE", "language":"de"}'
