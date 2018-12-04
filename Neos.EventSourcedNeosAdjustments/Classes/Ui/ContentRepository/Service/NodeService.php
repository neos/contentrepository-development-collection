<?php
namespace Neos\EventSourcedNeosAdjustments\Ui\ContentRepository\Service;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Transliterator\Transliterator;
use Neos\ContentRepository\DimensionSpace\Dimension\ContentDimensionIdentifier;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\ValueObject\NodeName;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Error\Messages\Error;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\TraversableNode;
use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddressFactory;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Locale;
use Neos\Flow\Utility\Algorithms;
use Neos\Neos\Service\TransliterationService;

/**
 * @Flow\Scope("singleton")
 */
class NodeService
{
    /**
     * @Flow\Inject
     * @var NodeAddressFactory
     */
    protected $nodeAddressFactory;

    /**
     * @Flow\Inject
     * @var ContentGraphInterface
     */
    protected $contentGraph;

    /**
     * @Flow\Inject
     * @var TransliterationService
     */
    protected $transliterationService;

    /**
     * Helper method to retrieve the closest document for a node
     *
     * @param TraversableNodeInterface $node
     * @return TraversableNodeInterface
     */
    public function getClosestDocument(TraversableNodeInterface $node)
    {
        if ($node->getNodeType()->isOfType('Neos.Neos:Document')) {
            return $node;
        }

        $flowQuery = new FlowQuery([$node]);

        return $flowQuery->closest('[instanceof Neos.Neos:Document]')->get(0);
    }

    /**
     * Helper method to check if a given node is a document node.
     *
     * @param  TraversableNodeInterface $node The node to check
     * @return boolean             A boolean which indicates if the given node is a document node.
     */
    public function isDocument(TraversableNodeInterface $node)
    {
        return ($this->getClosestDocument($node) === $node);
    }

    /**
     * Converts a given context path to a node object
     *
     * @param string $contextPath
     * @return TraversableNode|Error
     */
    public function getNodeFromContextPath($contextPath)
    {
        $nodeAddress = $this->nodeAddressFactory->createFromUriString($contextPath);
        $subgraph = $this->contentGraph
            ->getSubgraphByIdentifier($nodeAddress->getContentStreamIdentifier(), $nodeAddress->getDimensionSpacePoint(), VisibilityConstraints::withoutRestrictions());
        $node = $subgraph->findNodeByNodeAggregateIdentifier($nodeAddress->getNodeAggregateIdentifier());
        return new TraversableNode($node, $subgraph, VisibilityConstraints::withoutRestrictions());
    }

    /**
     * Generate a node name, optionally based on a suggested "ideal" name
     *
     * @param TraversableNodeInterface $parentNode
     * @return string
     */
    public function generateUniqueNodeName(TraversableNodeInterface $parentNode): string
    {

        $possibleNodeName = $this->generatePossibleNodeName();
        while ($parentNode->findNamedChildNode(new NodeName($possibleNodeName))) {
            $possibleNodeName = $this->generatePossibleNodeName();
        }

        return $possibleNodeName;
    }

    /**
     * Generates a URI path segment for a given node taking it's language dimension into account
     *
     * @param TraversableNodeInterface $node Optional node to determine language dimension
     * @param string $text Optional text
     * @return string
     * @throws \Neos\Flow\I18n\Exception\InvalidLocaleIdentifierException
     */
    public function generateUriPathSegment(?TraversableNodeInterface $node = null, ?string $text = ''): string
    {
        if ($node) {
            $text = $text === '' ? (string)($text ?: $node->getLabel() ?: $node->getNodeName()) : $text;
            $languageDimensionValue = $node->getDimensionSpacePoint()->getCoordinate(new ContentDimensionIdentifier('language'));
            if ($languageDimensionValue !== null) {
                $locale = new Locale($languageDimensionValue);
                $language = $locale->getLanguage();
            }
        }

        if ($text === '') {
            throw new \InvalidArgumentException('Given text was empty.', 1543916961);
        }
        $text = $this->transliterationService->transliterate($text, $language ?? null);

        return Transliterator::urlize($text);
    }

    /**
     * Generate possible node name in the form "node-alphanumeric".
     *
     * @return string
     */
    private function generatePossibleNodeName(): string
    {
        return 'node-' . Algorithms::generateRandomString(13, 'abcdefghijklmnopqrstuvwxyz0123456789');
    }
}
