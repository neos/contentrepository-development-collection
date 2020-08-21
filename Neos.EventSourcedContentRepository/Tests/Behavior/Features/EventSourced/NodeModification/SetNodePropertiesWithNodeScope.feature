@fixtures
Feature: Set properties of a node

  As a user of the CR I want to set properties of a node

  These are the test cases for node scoped properties

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
        bool:
          type: bool
        int:
          type: int
        float:
          type: float
        scopedString:
          type: string
          scope: node
        string:
          type: string
        array:
          type: array
        postalAddress:
          type: Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\PostalAddress
        date:
          type: DateTimeImmutable
        uri:
          type: GuzzleHttp\Psr7\Uri
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

  Scenario: Set node properties for node scoped properties
    When the command SetNodeProperties is executed with payload:
      | Key                       | Value                                                                                                                                                                                                                                                                                                              |
      | contentStreamIdentifier   | "cs-identifier"                                                                                                                                                                                                                                                                                                    |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"                                                                                                                                                                                                                                                                                                 |
      | originDimensionSpacePoint | {"market": "DE", "language": "de"}                                                                                                                                                                                                                                                                                 |
      | propertyValues            | {"scopedString":"Nody McNodeface", "string":"Nody McNodeface", "bool":true, "int":42, "float":4.2, "array":{"givenName":"Nody", "familyName":"McNodeface"}, "postalAddress":"VO:PostalAddress", "date":"Date:2020-08-20T18:56:15+00:00", "uri":"URI:https://neos.io", "image":"IMG:dummy", "images":"[IMG:dummy]"} |
    And the graph projection is fully up to date
    Then I am in content stream "cs-identifier" and Dimension Space Point {"market": "DE", "language": "de"}
    And I expect a node identified by aggregate identifier "nody-mc-nodeface" to exist in the subgraph
    And I expect this node to have the properties:
      | Key           | Type                                                                            | Value                                           |
      | bool          | bool                                                                            | true                                            |
      | int           | int                                                                             | 42                                              |
      | float         | float                                                                           | 4.2                                             |
      | scopedString  | string                                                                          | "Nody McNodeface"                               |
      | string        | string                                                                          | "Nody McNodeface"                               |
      | array         | array                                                                           | {"givenName":"Nody", "familyName":"McNodeface"} |
      | postalAddress | Neos\EventSourcedContentRepository\Tests\Behavior\Features\Helper\PostalAddress | "VO:PostalAddress"                              |
      | date          | DateTimeImmutable                                                               | "2020-08-20T18:56:15+00:00"                     |
      | uri           | GuzzleHttp\Psr7\Uri                                                             | "https://neos.io"                               |
      | image         | Neos\Media\Domain\Model\ImageInterface                                          | "IMG:dummy"                                     |
      | images        | array<Neos\Media\Domain\Model\ImageInterface>                                   | "[IMG:dummy]"                                   |

