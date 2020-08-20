@fixtures
Feature: Set properties of a node

  As a user of the CR I want to set properties of a node

  These are the base test cases for the NodeAggregateCommandHandler to block invalid commands

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values      | Generalizations |
      | market     | DE      | DE, CH      | CH->DE          |
      | language   | de      | de, gsw, fr | gsw->de         |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Document':
      properties:
        string:
          type: string
        bool:
          type: bool
        int:
          type: int
        float:
          type: float
        array:
          type: array
        postalAddress:
          type: Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\PostalAddress
        date:
          type: DateTimeImmutable
        uri:
          type: Psr\Http\Message\UriInterface
        image:
          type: Neos\Media\Domain\Model\ImageInterface
        images:
          type: array<Neos\Media\Domain\Model\ImageInterface>
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                        | Value                                  |
      | workspaceName              | "live"                                 |
      | workspaceTitle             | "Live"                                 |
      | workspaceDescription       | "The live workspace"                   |
      | initiatingUserIdentifier   | "00000000-0000-0000-0000-000000000000" |
      | newContentStreamIdentifier | "cs-identifier"                        |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                                                                                                   |
      | contentStreamIdentifier     | "cs-identifier"                                                                                                                         |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                                                                                                |
      | nodeTypeName                | "Neos.ContentRepository:Root"                                                                                                           |
      | coveredDimensionSpacePoints | [{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
      | initiatingUserIdentifier    | "00000000-0000-0000-0000-000000000000"                                                                                                  |
      | nodeAggregateClassification | "root"                                                                                                                                  |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                                                                                   |
      | contentStreamIdentifier       | "cs-identifier"                                                                                                                         |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                                                                                                                      |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                                                                                               |
      | originDimensionSpacePoint     | {"market":"DE", "language":"de"}                                                                                                        |
      | coveredDimensionSpacePoints   | [{"market":"DE", "language":"de"},{"market":"DE", "language":"gsw"},{"market":"CH", "language":"de"},{"market":"CH", "language":"gsw"}] |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                                                                                                |
      | nodeName                      | "document"                                                                                                                              |
      | nodeAggregateClassification   | "regular"                                                                                                                               |
    And the graph projection is fully up to date

  Scenario: Try to set a node property in a non-existing content stream:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "non-existing"                     |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": "Nody McNodeface"}      |
    Then the last command should have thrown an exception of type "ContentStreamDoesNotExistYet"

  Scenario: Try to set a node property of a non-existing node aggregate:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "i-do-not-exist"                   |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": "Nody McNodeface"}      |
    Then the last command should have thrown an exception of type "NodeAggregateCurrentlyDoesNotExist"

  Scenario: Try to set a node property of a root node aggregate:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                            |
      | contentStreamIdentifier   | "cs-identifier"                  |
      | nodeAggregateIdentifier   | "lady-eleonode-rootford"         |
      | originDimensionSpacePoint | {"market":"DE", "language":"de"} |
      | propertyValues            | {"string": "Nody McNodeface"}    |
    Then the last command should have thrown an exception of type "NodeAggregateIsRoot"

  Scenario: Try to set a node property in a non-existing dimension space point:
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                     |
      | contentStreamIdentifier   | "cs-identifier"                           |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                        |
      | originDimensionSpacePoint | {"market": "nope", "language": "neither"} |
      | propertyValues            | {"string": "Nody McNodeface"}             |
    Then the last command should have thrown an exception of type "DimensionSpacePointNotFound"

  Scenario: Try to set a node property in a dimension space point the aggregate does not occupy
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "fr"} |
      | propertyValues            | {"string": "Nody McNodeface"}      |
    Then the last command should have thrown an exception of type "DimensionSpacePointIsNotYetOccupied"

  Scenario: Try to set a node property the aggregate's node type does not define
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"undefinedProperty": "value"}     |
    Then the last command should have thrown an exception of type "NodeTypeDoesNotDeclareProperty"

  Scenario: Try to set a bool node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"bool": 1}                        |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"bool": 1.2}                      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                       |
      | contentStreamIdentifier   | "cs-identifier"                                             |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                          |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                          |
      | propertyValues            | {"bool": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to an array value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                       |
      | contentStreamIdentifier   | "cs-identifier"                                             |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                          |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                          |
      | propertyValues            | {"bool": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to a value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"bool": "VO:PostalAddress"}       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to a DateTimeImmutable
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"bool": "DT:2020-08-20T18:56:15"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"bool": "URI:https://neos.io"}    |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"bool": "IMG:dummy"}              |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a bool node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"bool": "[IMG:dummy]"}            |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to a boolean value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": true}                      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": 1.2}                       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": "Nody McNodeface"}         |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                      |
      | contentStreamIdentifier   | "cs-identifier"                                            |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                         |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                         |
      | propertyValues            | {"int": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to a value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": "VO:PostalAddress"}        |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to a DateTimeImmutable
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": "DT:2020-08-20T18:56:15"}  |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": "URI:https://neos.io"}     |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an int node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": "IMG:dummy"}               |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a int node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"int": "[IMG:dummy]"}             |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"float": true}                    |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"float": 1}                       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"float": "Nody McNodeface"}       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                        |
      | contentStreamIdentifier   | "cs-identifier"                                              |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                           |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                           |
      | propertyValues            | {"float": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to a value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"float": "VO:PostalAddress"}      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to a DateTimeImmutable
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                               |
      | contentStreamIdentifier   | "cs-identifier"                     |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                  |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}  |
      | propertyValues            | {"float": "DT:2020-08-20T18:56:15"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"float": "URI:https://neos.io"}   |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"float": "IMG:dummy"}             |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a float node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"float": "[IMG:dummy]"}           |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": true}                   |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to an integer value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": 1}                      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": 1.2}                    |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                         |
      | contentStreamIdentifier   | "cs-identifier"                                               |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                            |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                            |
      | propertyValues            | {"string": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to a value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": "VO:PostalAddress"}     |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to a DateTimeImmutable
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                |
      | contentStreamIdentifier   | "cs-identifier"                      |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                   |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}   |
      | propertyValues            | {"string": "DT:2020-08-20T18:56:15"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": "URI:https://neos.io"}  |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": "IMG:dummy"}            |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a string node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"string": "[IMG:dummy]"}          |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"array": true}                    |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"array": 1}                       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"array": 1.2}                     |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"array": "Nody McNodeface"}       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to a value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"array": "VO:PostalAddress"}      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to a DateTimeImmutable
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                               |
      | contentStreamIdentifier   | "cs-identifier"                     |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                  |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}  |
      | propertyValues            | {"array": "DT:2020-08-20T18:56:15"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"array": "URI:https://neos.io"}   |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a array node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"array": "IMG:dummy"}             |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"postalAddress": true}            |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"postalAddress": 1}               |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"postalAddress": 1.2}             |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                |
      | contentStreamIdentifier   | "cs-identifier"                      |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                   |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}   |
      | propertyValues            | {"postalAddress": "Nody McNodeface"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                                |
      | contentStreamIdentifier   | "cs-identifier"                                                      |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                                   |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                                   |
      | propertyValues            | {"postalAddress": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to a DateTimeImmutable
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                       |
      | contentStreamIdentifier   | "cs-identifier"                             |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                          |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}          |
      | propertyValues            | {"postalAddress": "DT:2020-08-20T18:56:15"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                    |
      | contentStreamIdentifier   | "cs-identifier"                          |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                       |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}       |
      | propertyValues            | {"postalAddress": "URI:https://neos.io"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"postalAddress": "IMG:dummy"}     |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a value object node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"postalAddress": "[IMG:dummy]"}   |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": true}                     |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": 1}                        |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": 1.2}                      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": "Nody McNodeface"}        |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                       |
      | contentStreamIdentifier   | "cs-identifier"                                             |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                          |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                          |
      | propertyValues            | {"date": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to another value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": "VO:PostalAddress"}       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": "URI:https://neos.io"}    |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": "IMG:dummy"}              |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a date node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"date": "[IMG:dummy]"}            |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": true}                      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": 1}                         |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": 1.2}                       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": "Nody McNodeface"}         |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                      |
      | contentStreamIdentifier   | "cs-identifier"                                            |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                         |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                         |
      | propertyValues            | {"uri": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to another value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": "VO:PostalAddress"}        |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to a date
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": "DT:2020-08-20T18:56:15"}  |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": "IMG:dummy"}               |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set a uri node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"uri": "[IMG:dummy]"}             |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"image": true}                    |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"image": 1}                       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"image": 1.2}                     |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"image": "Nody McNodeface"}       |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                        |
      | contentStreamIdentifier   | "cs-identifier"                                              |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                           |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                           |
      | propertyValues            | {"image": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to another value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"image": "VO:PostalAddress"}      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to a date
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                               |
      | contentStreamIdentifier   | "cs-identifier"                     |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                  |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}  |
      | propertyValues            | {"image": "DT:2020-08-20T18:56:15"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"image": "URI:https://neos.io"}   |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an entity node property to an array of entities
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"image": "[IMG:dummy]"}           |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to a bool value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"images": true}                   |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to an int value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"images": 1}                      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to a float value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"images": 1.2}                    |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to a string value
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"images": "Nody McNodeface"}      |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to an array
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                                         |
      | contentStreamIdentifier   | "cs-identifier"                                               |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                            |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                            |
      | propertyValues            | {"images": {"givenName": "Nody", "familyName": "McNodeFace"}} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to another value object
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"images": "VO:PostalAddress"}     |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to a date
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                                |
      | contentStreamIdentifier   | "cs-identifier"                      |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                   |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}   |
      | propertyValues            | {"images": "DT:2020-08-20T18:56:15"} |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to a URI
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"images": "URI:https://neos.io"}  |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"

  Scenario: Try to set an array of entities node property to an entity
    When the command SetNodeProperties is executed with payload and exceptions are caught:
      | Key                       | Value                              |
      | contentStreamIdentifier   | "cs-identifier"                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"} |
      | propertyValues            | {"images": "IMG:dummy"}            |
    Then the last command should have thrown an exception of type "PropertyTypeDoesNotMatchNodeTypeSchema"
