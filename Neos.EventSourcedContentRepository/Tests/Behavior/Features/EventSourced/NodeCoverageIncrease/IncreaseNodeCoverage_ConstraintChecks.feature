@fixtures
Feature: Increase node aggregate coverage

  As a user of the CR I want to increase the dimension space coverage
  of a given node aggregate
  in a given content stream
  using a given origin dimension space point
  by a given dimension space point set
  recursively or not.

  Background:
    Given I have the following content dimensions:
      | Identifier | Default | Values                | Generalizations                     |
      | language   | mul     | mul, de, en, gsw, ltz | gsw->de->mul, ltz->de->mul, en->mul |
    And I have the following NodeTypes configuration:
    """
    'Neos.ContentRepository:Root': []
    'Neos.ContentRepository.Testing:Node': []
    'Neos.ContentRepository.Testing:Document':
      childNodes:
        tethered:
          type: 'Neos.ContentRepository.Testing:Tethered'
    'Neos.ContentRepository.Testing:Tethered': []
    """
    And the event RootWorkspaceWasCreated was published with payload:
      | Key                        | Value                        |
      | workspaceName              | "live"                       |
      | workspaceTitle             | "Live"                       |
      | workspaceDescription       | "The live workspace"         |
      | newContentStreamIdentifier | "cs-identifier"              |
      | initiatingUserIdentifier   | "initiating-user-identifier" |
    And the event RootNodeAggregateWithNodeWasCreated was published with payload:
      | Key                         | Value                                                                                          |
      | contentStreamIdentifier     | "cs-identifier"                                                                                |
      | nodeAggregateIdentifier     | "lady-eleonode-rootford"                                                                       |
      | nodeTypeName                | "Neos.ContentRepository:Root"                                                                  |
      | coveredDimensionSpacePoints | [{"language":"mul"},{"language":"de"},{"language":"en"},{"language":"gsw"},{"language":"ltz"}] |
      | nodeAggregateClassification | "root"                                                                                         |
      | initiatingUserIdentifier    | "initiating-user-identifier"                                                                   |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                       |
      | contentStreamIdentifier       | "cs-identifier"                                                             |
      | nodeAggregateIdentifier       | "sir-david-nodenborough"                                                    |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Document"                                   |
      | originDimensionSpacePoint     | {"language":"mul"}                                                          |
      | coveredDimensionSpacePoints   | [{"language":"mul"},{"language":"en"},{"language":"de"},{"language":"gsw"}] |
      | parentNodeAggregateIdentifier | "lady-eleonode-rootford"                                                    |
      | nodeName                      | "document"                                                                  |
      | nodeAggregateClassification   | "regular"                                                                   |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                                                       |
      | contentStreamIdentifier       | "cs-identifier"                                                             |
      | nodeAggregateIdentifier       | "nodewyn-tetherton"                                                         |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Tethered"                                   |
      | originDimensionSpacePoint     | {"language":"mul"}                                                          |
      | coveredDimensionSpacePoints   | [{"language":"mul"},{"language":"en"},{"language":"de"},{"language":"gsw"}] |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"                                                    |
      | nodeName                      | "tethered"                                                                  |
      | nodeAggregateClassification   | "tethered"                                                                  |
    And the event NodeSpecializationVariantWasCreated was published with payload:
      | Key                     | Value                    |
      | contentStreamIdentifier | "cs-identifier"          |
      | nodeAggregateIdentifier | "sir-david-nodenborough" |
      | sourceOrigin            | {"language":"mul"}       |
      | specializationOrigin    | {"language":"en"}        |
      | specializationCoverage  | [{"language":"en"}]      |
    And the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                 |
      | contentStreamIdentifier       | "cs-identifier"                       |
      | nodeAggregateIdentifier       | "nody-mc-nodeface"                    |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Node" |
      | originDimensionSpacePoint     | {"language":"de"}                     |
      | coveredDimensionSpacePoints   | [{"language":"de"}]                   |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"              |
      | nodeName                      | "child-document"                      |
      | nodeAggregateClassification   | "regular"                             |
    And the graph projection is fully up to date

  Scenario: Try to increase node aggregate coverage in a non-existent content stream
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                 |
      | contentStreamIdentifier   | "does-not-exist"      |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"    |
      | originDimensionSpacePoint | {"language": "de"}    |
      | additionalCoverage        | [{"language": "gsw"}] |
      | recursive                 | false                 |
    Then the last command should have thrown an exception of type "ContentStreamDoesNotExistYet"

  Scenario: Try to increase node aggregate coverage of a non-existent node aggregate
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                 |
      | contentStreamIdentifier   | "cs-identifier"       |
      | nodeAggregateIdentifier   | "i-do-not-exist"      |
      | originDimensionSpacePoint | {"language": "de"}    |
      | additionalCoverage        | [{"language": "gsw"}] |
      | recursive                 | false                 |
    Then the last command should have thrown an exception of type "NodeAggregateCurrentlyDoesNotExist"

  Scenario: Try to increase node aggregate coverage using a origin dimension space point the node aggregate does not occupy
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                 |
      | contentStreamIdentifier   | "cs-identifier"       |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"    |
      | originDimensionSpacePoint | {"language": "mul"}   |
      | additionalCoverage        | [{"language": "gsw"}] |
      | recursive                 | false                 |
    Then the last command should have thrown an exception of type "DimensionSpacePointIsNotYetOccupied"

  Scenario: Try to increase node aggregate coverage to a dimension space point that is not a specialization of the origin dimension space point
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                |
      | contentStreamIdentifier   | "cs-identifier"      |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"   |
      | originDimensionSpacePoint | {"language": "de"}   |
      | additionalCoverage        | [{"language": "en"}] |
      | recursive                 | false                |
    Then the last command should have thrown an exception of type "DimensionSpacePointIsNoSpecialization"

  Scenario: Try to increase node aggregate coverage by a dimension space point the parent node aggregate does not cover
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                 |
      | contentStreamIdentifier   | "cs-identifier"       |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"    |
      | originDimensionSpacePoint | {"language": "de"}    |
      | additionalCoverage        | [{"language": "ltz"}] |
      | recursive                 | false                 |
    Then the last command should have thrown an exception of type "NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet"

  Scenario: Try to increase node aggregate coverage to a dimension space point that is already covered
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                    |
      | contentStreamIdentifier   | "cs-identifier"          |
      | nodeAggregateIdentifier   | "sir-david-nodenborough" |
      | originDimensionSpacePoint | {"language": "mul"}      |
      | additionalCoverage        | [{"language": "de"}]     |
      | recursive                 | false                    |
    Then the last command should have thrown an exception of type "NodeAggregateDoesCurrentlyCoverDimensionSpacePointSet"

  Scenario: Try to increase node aggregate coverage of a root node aggregate
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                    |
      | contentStreamIdentifier   | "cs-identifier"          |
      | nodeAggregateIdentifier   | "lady-eleonode-rootford" |
      | originDimensionSpacePoint | {"language": "mul"}      |
      | additionalCoverage        | [{"language": "de"}]     |
      | recursive                 | false                    |
    Then the last command should have thrown an exception of type "NodeAggregateIsRoot"

  Scenario: Try to increase node aggregate coverage of a tethered node aggregate
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                |
      | contentStreamIdentifier   | "cs-identifier"      |
      | nodeAggregateIdentifier   | "nodewyn-tetherton"  |
      | originDimensionSpacePoint | {"language": "mul"}  |
      | additionalCoverage        | [{"language": "de"}] |
      | recursive                 | false                |
    Then the last command should have thrown an exception of type "NodeAggregateIsTethered"

  Scenario: Try to increase node aggregate coverage by a dimension space point where the name is already taken
    When the event NodeAggregateWithNodeWasCreated was published with payload:
      | Key                           | Value                                 |
      | contentStreamIdentifier       | "cs-identifier"                       |
      | nodeAggregateIdentifier       | "nodys-evil-twin"                     |
      | nodeTypeName                  | "Neos.ContentRepository.Testing:Node" |
      | originDimensionSpacePoint     | {"language":"gsw"}                    |
      | coveredDimensionSpacePoints   | [{"language":"gsw"}]                  |
      | parentNodeAggregateIdentifier | "sir-david-nodenborough"              |
      | nodeName                      | "child-document"                      |
      | nodeAggregateClassification   | "regular"                             |
    And the graph projection is fully up to date
    When the command IncreaseNodeAggregateCoverage is executed with payload and exceptions are caught:
      | Key                       | Value                 |
      | contentStreamIdentifier   | "cs-identifier"       |
      | nodeAggregateIdentifier   | "nody-mc-nodeface"    |
      | originDimensionSpacePoint | {"language": "de"}    |
      | additionalCoverage        | [{"language": "gsw"}] |
      | recursive                 | false                 |
    Then the last command should have thrown an exception of type "NodeNameIsAlreadyCovered"

    # Tethered child aggregates always cover the same dimension space point set,
    # thus there can never be nodes covering the name of tethered children.
