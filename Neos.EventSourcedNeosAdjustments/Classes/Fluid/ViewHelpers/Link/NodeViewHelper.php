<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Fluid\ViewHelpers\Link;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\ContentSubgraph\NodePath;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\ContentAccess\NodeAccessorInterface;
use Neos\EventSourcedContentRepository\ContentAccess\NodeAccessorManager;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\Exception\NodeAddressCannotBeSerializedException;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddressFactory;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeSiteResolvingService;
use Neos\EventSourcedNeosAdjustments\Domain\Service\NodeShortcutResolver;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Exception\InvalidShortcutException;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\NodeUriBuilder;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Exception as HttpException;
use Neos\Flow\Mvc\Exception\NoMatchingRouteException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractTagBasedViewHelper;
use Neos\FluidAdaptor\Core\ViewHelper\Exception as ViewHelperException;
use Neos\Fusion\ViewHelpers\FusionContextTrait;
use Neos\Neos\Controller\Exception\NodeNotFoundException;

/**
 * A view helper for creating links with URIs pointing to nodes.
 *
 * The target node can be provided as string or as a Node object; if not specified
 * at all, the generated URI will refer to the current document node inside the Fusion context.
 *
 * When specifying the ``node`` argument as string, the following conventions apply:
 *
 * *``node`` starts with ``/``:*
 * The given path is an absolute node path and is treated as such.
 * Example: ``/sites/acmecom/home/about/us``
 *
 * *``node`` does not start with ``/``:*
 * The given path is treated as a path relative to the current node.
 * Examples: given that the current node is ``/sites/acmecom/products/``,
 * ``stapler`` results in ``/sites/acmecom/products/stapler``,
 * ``../about`` results in ``/sites/acmecom/about/``,
 * ``./neos/info`` results in ``/sites/acmecom/products/neos/info``.
 *
 * *``node`` starts with a tilde character (``~``):*
 * The given path is treated as a path relative to the current site node.
 * Example: given that the current node is ``/sites/acmecom/products/``,
 * ``~/about/us`` results in ``/sites/acmecom/about/us``,
 * ``~`` results in ``/sites/acmecom``.
 *
 * = Examples =
 *
 * <code title="Defaults">
 * <neos:link.node>some link</neos:link.node>
 * </code>
 * <output>
 * <a href="sites/mysite.com/homepage/about.html">some link</a>
 * (depending on current node, format etc.)
 * </output>
 *
 * <code title="Generating a link with an absolute URI">
 * <neos:link.node absolute="{true}">bookmark this page</neos:link.node>
 * </code>
 * <output>
 * <a href="http://www.example.org/homepage/about.html">bookmark this page</a>
 * (depending on current workspace, current node, format, host etc.)
 * </output>
 *
 * <code title="Target node given as absolute node path">
 * <neos:link.node node="/sites/exampleorg/contact/imprint">Corporate imprint</neos:link.node>
 * </code>
 * <output>
 * <a href="contact/imprint.html">Corporate imprint</a>
 * (depending on current workspace, current node, format etc.)
 * </output>
 *
 * <code title="Target node given as node://-uri">
 * <neos:link.node node="node://30e893c1-caef-0ca5-b53d-e5699bb8e506">Corporate imprint</neos:link.node>
 * </code>
 * <output>
 * <a href="contact/imprint.html">Corporate imprint</a>
 * (depending on current workspace, current node, format etc.)
 * </output>
 *
 * <code title="Target node given as relative node path">
 * <neos:link.node node="~/about/us">About us</neos:link.node>
 * </code>
 * <output>
 * <a href="about/us.html">About us</a>
 * (depending on current workspace, current node, format etc.)
 * </output>
 *
 * <code title="Node label as tag content">
 * <neos:link.node node="/sites/exampleorg/contact/imprint" />
 * </code>
 * <output>
 * <a href="contact/imprint.html">Imprint</a>
 * (depending on current workspace, current node, format etc.)
 * </output>
 *
 * <code title="Dynamic tag content involving the linked node's properties">
 * <neos:link.node node="about-us">see our <span>{linkedNode.label}</span> page</neos:link.node>
 * </code>
 * <output>
 * <a href="about-us.html">see our <span>About Us</span> page</a>
 * (depending on current workspace, current node, format etc.)
 * </output>
 *
 * @api
 */
class NodeViewHelper extends AbstractTagBasedViewHelper
{
    use FusionContextTrait;

    /**
     * @var string
     */
    protected $tagName = 'a';

    /**
     * @Flow\Inject
     * @var NodeSiteResolvingService
     */
    protected $nodeSiteResolvingService;

    /**
     * @Flow\Inject
     * @var NodeAccessorManager
     */
    protected $nodeAccessorManager;

    /**
     * @Flow\Inject
     * @var NodeShortcutResolver
     */
    protected $nodeShortcutResolver;

    /**
     * @Flow\Inject
     * @var NodeAddressFactory
     */
    protected $nodeAddressFactory;

    /**
     * Initialize arguments
     *
     * @return void
     * @throws ViewHelperException
     */
    public function initializeArguments()
    {
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute(
            'name',
            'string',
            'Specifies the name of an anchor'
        );
        $this->registerTagAttribute(
            'rel',
            'string',
            'Specifies the relationship between the current document and the linked document'
        );
        $this->registerTagAttribute(
            'rev',
            'string',
            'Specifies the relationship between the linked document and the current document'
        );
        $this->registerTagAttribute(
            'target',
            'string',
            'Specifies where to open the linked document'
        );

        $this->registerArgument(
            'node',
            'mixed',
            'A node object, a string node path (absolute or relative), a string node://-uri or NULL'
        );
        $this->registerArgument(
            'format',
            'string',
            'Format to use for the URL, for example "html" or "json"'
        );
        $this->registerArgument(
            'absolute',
            'boolean',
            'If set, an absolute URI is rendered',
            false,
            false
        );
        $this->registerArgument(
            'arguments',
            'array',
            'Additional arguments to be passed to the UriBuilder (for example pagination parameters)',
            false,
            []
        );
        $this->registerArgument(
            'section',
            'string',
            'The anchor to be added to the URI',
            false,
            ''
        );
        $this->registerArgument(
            'addQueryString',
            'boolean',
            'If set, the current query parameters will be kept in the URI',
            false,
            false
        );
        $this->registerArgument(
            'argumentsToBeExcludedFromQueryString',
            'array',
            'arguments to be removed from the URI. Only active if $addQueryString = true',
            false,
            []
        );
        $this->registerArgument(
            'baseNodeName',
            'string',
            'The name of the base node inside the Fusion context to use for the ContentContext'
                . ' or resolving relative paths',
            false,
            'documentNode'
        );
        $this->registerArgument(
            'nodeVariableName',
            'string',
            'The variable the node will be assigned to for the rendered child content',
            false,
            'linkedNode'
        );
    }

    /**
     * Renders the link. Renders the linked node's label if there's no child content.
     *
     * @return string The rendered link
     * @throws ViewHelperException
     */
    public function render(): string
    {
        $node = $this->arguments['node'];
        if (!$node instanceof NodeInterface) {
            $node = $this->getContextVariable($this->arguments['baseNodeName']);
        }

        if ($node instanceof NodeInterface) {
            $nodeAddress = $this->nodeAddressFactory->createFromNode($node);
        } elseif (is_string($node)) {
            $nodeAddress = $this->resolveNodeAddressFromString($node);
        } else {
            throw new ViewHelperException(sprintf(
                'The "node" argument can only be a string or an instance of %s. Given: %s',
                NodeInterface::class,
                is_object($node) ? get_class($node) : gettype($node)
            ), 1601372376);
        }

        $nodeAccessor = $this->getNodeAccessorForNodeAddress($nodeAddress);
        $resolvedNode = $nodeAccessor->findByIdentifier($nodeAddress->nodeAggregateIdentifier);
        if ($resolvedNode === null) {
            throw new ViewHelperException(sprintf(
                'Failed to resolve node "%s" on subgraph "%s"',
                $nodeAddress->nodeAggregateIdentifier,
                json_encode($nodeAccessor, JSON_PARTIAL_OUTPUT_ON_ERROR)
            ), 1601372444);
        }
        if ($resolvedNode->getNodeType()->isOfType('Neos.Neos:Shortcut')) {
            try {
                $shortcutNodeAddress = $this->nodeShortcutResolver->resolveShortcutTarget($nodeAddress);
            } catch (NodeNotFoundException | InvalidShortcutException $e) {
                throw new ViewHelperException(sprintf(
                    'Failed to resolve shortcut node "%s" on subgraph "%s"',
                    $resolvedNode->getNodeAggregateIdentifier(),
                    json_encode($nodeAccessor, JSON_PARTIAL_OUTPUT_ON_ERROR)
                ), 1601370239, $e);
            }
            if ($shortcutNodeAddress instanceof NodeAddress) {
                $resolvedNode = $nodeAccessor->findByIdentifier($shortcutNodeAddress->nodeAggregateIdentifier);
            }
        }

        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($this->controllerContext->getRequest()->getMainRequest());
        $uriBuilder->setFormat($this->arguments['format'])
            ->setCreateAbsoluteUri($this->arguments['absolute'])
            ->setArguments($this->arguments['arguments'])
            ->setSection($this->arguments['section'])
            ->setAddQueryString($this->arguments['addQueryString'])
            ->setArgumentsToBeExcludedFromQueryString($this->arguments['argumentsToBeExcludedFromQueryString']);

        try {
            $uri = (string)NodeUriBuilder::fromUriBuilder($uriBuilder)->uriFor($nodeAddress);
        } catch (NodeAddressCannotBeSerializedException | HttpException
            | NoMatchingRouteException | MissingActionNameException $e) {
            throw new ViewHelperException(sprintf(
                'Failed to build URI for node: %s: %s',
                $nodeAddress,
                $e->getMessage()
            ), 1601372594, $e);
        }
        $this->tag->addAttribute('href', $uri);

        $this->templateVariableContainer->add($this->arguments['nodeVariableName'], $resolvedNode);
        $content = $this->renderChildren();
        $this->templateVariableContainer->remove($this->arguments['nodeVariableName']);

        if ($content === null && $resolvedNode !== null) {
            $content = $resolvedNode->getLabel();
        }

        $this->tag->setContent($content);
        $this->tag->forceClosingTag(true);
        return $this->tag->render();
    }

    /**
     * Converts strings like "relative/path", "/absolute/path", "~/site-relative/path"
     * and "~" to the corresponding NodeAddress
     *
     * @param string $path
     * @return NodeAddress
     * @throws ViewHelperException
     */
    private function resolveNodeAddressFromString(string $path): NodeAddress
    {
        /* @var NodeInterface $documentNode */
        $documentNode = $this->getContextVariable('documentNode');
        $documentNodeAddress = $this->nodeAddressFactory->createFromNode($documentNode);
        if (strncmp($path, 'node://', 7) === 0) {
            return $documentNodeAddress->withNodeAggregateIdentifier(
                NodeAggregateIdentifier::fromString(\mb_substr($path, 7))
            );
        }
        $nodeAccessor = $this->getNodeAccessorForNodeAddress($documentNodeAddress);
        if (strncmp($path, '~', 1) === 0) {
            // TODO: This can be simplified
            // once https://github.com/neos/contentrepository-development-collection/issues/164 is resolved
            $siteNode = $this->nodeSiteResolvingService->findSiteNodeForNodeAddress($documentNodeAddress);
            if ($siteNode === null) {
                throw new ViewHelperException(sprintf(
                    'Failed to determine site node for aggregate node "%s" and subgraph "%s"',
                    $documentNodeAddress->nodeAggregateIdentifier,
                    json_encode($nodeAccessor, JSON_PARTIAL_OUTPUT_ON_ERROR)
                ), 1601366598);
            }
            if ($path === '~') {
                $targetNode = $siteNode;
            } else {
                $targetNode = $nodeAccessor->findNodeByPath(NodePath::fromString(substr($path, 1)), $siteNode);
            }
        } else {
            $targetNode = $nodeAccessor->findNodeByPath(NodePath::fromString($path), $documentNode);
        }
        if ($targetNode === null) {
            throw new ViewHelperException(sprintf(
                'Node on path "%s" could not be found for aggregate node "%s" and subgraph "%s"',
                $path,
                $documentNodeAddress->nodeAggregateIdentifier,
                json_encode($nodeAccessor, JSON_PARTIAL_OUTPUT_ON_ERROR)
            ), 1601311789);
        }
        return $documentNodeAddress->withNodeAggregateIdentifier($targetNode->getNodeAggregateIdentifier());
    }

    /**
     * Returns the ContentSubgraph that is specified via "subgraph" argument, if present.
     * Otherwise the Subgraph of the given $nodeAddress
     *
     * @param NodeAddress $nodeAddress
     * @return NodeAccessorInterface
     */
    private function getNodeAccessorForNodeAddress(NodeAddress $nodeAddress): NodeAccessorInterface
    {
        return $this->nodeAccessorManager->accessorFor(
            $nodeAddress->contentStreamIdentifier,
            $nodeAddress->dimensionSpacePoint,
            VisibilityConstraints::withoutRestrictions()
        );
    }
}
