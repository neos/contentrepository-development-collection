<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\Exception\NodeAddressCannotBeSerializedException;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedContentRepository\Domain\ValueObject\WorkspaceName;
use Neos\EventSourcedNeosAdjustments\Domain\Service\NodeShortcutResolver;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ContentDimensionProcessor\ContentDimensionProcessorInterface;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Exception\InvalidShortcutException;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Exception\InvalidContentDimensionValueUriProcessorException;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Foo\FooInterface;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Projection\DocumentUriPathFinder;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\AbstractRoutePart;
use Neos\Flow\Mvc\Routing\Dto\MatchResult;
use Neos\Flow\Mvc\Routing\Dto\ResolveResult;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Dto\UriConstraints;
use Neos\Flow\Mvc\Routing\DynamicRoutePartInterface;
use Neos\Flow\Mvc\Routing\ParameterAwareRoutePartInterface;
use Neos\Neos\Controller\Exception\NodeNotFoundException;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Routing\FrontendNodeRoutePartHandlerInterface;
use Psr\Http\Message\UriInterface;

/**
 * A route part handler for finding nodes in the website's frontend.
 * Uses a special projection {@see DocumentUriPathFinder}, and does NOT use the graph projection in any way.
 *
 * @Flow\Scope("singleton")
 */
final class EventSourcedFrontendNodeRoutePartHandler extends AbstractRoutePart implements DynamicRoutePartInterface, ParameterAwareRoutePartInterface, FrontendNodeRoutePartHandlerInterface
{
    private string $splitString = '';

    private DocumentUriPathFinder $documentUriPathFinder;
    private ContentDimensionProcessorInterface $contentDimensionProcessor;
    private FooInterface $foo;
    private NodeShortcutResolver $nodeShortcutResolver;

    public function __construct(DocumentUriPathFinder $documentUriPathFinder, ContentDimensionProcessorInterface $contentDimensionProcessor, FooInterface $foo, NodeShortcutResolver $nodeShortcutResolver)
    {
        $this->documentUriPathFinder = $documentUriPathFinder;
        $this->contentDimensionProcessor = $contentDimensionProcessor;
        $this->foo = $foo;
        $this->nodeShortcutResolver = $nodeShortcutResolver;
    }

    /**
     * @param mixed $requestPath
     * @param RouteParameters $parameters
     * @return bool|MatchResult
     * @throws NodeAddressCannotBeSerializedException
     */
    public function matchWithParameters(&$requestPath, RouteParameters $parameters)
    {
        if (!is_string($requestPath)) {
            return false;
        }
        $dimensionSpacePoint = $this->contentDimensionProcessor->resolveDimensionSpacePoint($requestPath, $parameters);
        $remainingRequestPath = $this->truncateRequestPathAndReturnRemainder($requestPath);
        $siteNodeName = $this->foo->getCurrentSiteNode($parameters);
        try {
            $matchResult = $this->matchUriPath($requestPath, $dimensionSpacePoint, $siteNodeName);
        } catch (NodeNotFoundException $exception) {
            // we silently swallow the Node Not Found case, as you'll see this in the server log if it interests you
            // (and other routes could still handle this).
            return false;
        }
        $requestPath = $remainingRequestPath;
        return $matchResult;
    }

    /**
     * @param array $routeValues
     * @param RouteParameters $parameters
     * @return ResolveResult|bool
     */
    public function resolveWithParameters(array &$routeValues, RouteParameters $parameters)
    {
        if ($this->name === null || $this->name === '' || !\array_key_exists($this->name, $routeValues)) {
            return false;
        }

        $nodeAddress = $routeValues[$this->name];
        if (!$nodeAddress instanceof NodeAddress) {
            return false;
        }
        // TODO exception => return false
        $currentSiteNodeName = $this->foo->getCurrentSiteNode($parameters);

        try {
            $resolveResult = $this->resolveNodeAddress($nodeAddress, $currentSiteNodeName);
        } catch (NodeNotFoundException | InvalidShortcutException $exception) {
            // TODO log exception
            return false;
        }

        unset($routeValues[$this->name]);
        return $resolveResult;
    }

    /**
     * @param NodeAddress $nodeAddress
     * @param NodeName $currentSiteNodeName
     * @return ResolveResult
     * @throws NodeNotFoundException | InvalidShortcutException
     */
    private function resolveNodeAddress(NodeAddress $nodeAddress, NodeName $currentSiteNodeName): ResolveResult
    {
        $nodeInfo = $this->documentUriPathFinder->getByIdAndDimensionSpacePointHash($nodeAddress->getNodeAggregateIdentifier(), $nodeAddress->getDimensionSpacePoint()->getHash());
        if ($nodeInfo->isDisabled()) {
            throw new NodeNotFoundException(sprintf('The resolved node for address %s is disabled', $nodeAddress), 1599668357);
        }
        if ($nodeInfo->isShortcut()) {
            $nodeInfo = $this->nodeShortcutResolver->resolveNode($nodeInfo);
            if ($nodeInfo instanceof UriInterface) {
                return $this->buildResolveResultFromUri($nodeInfo);
            }
            $nodeAddress = $nodeAddress->withNodeAggregateIdentifier($nodeInfo->getNodeAggregateIdentifier());
        }
        $uriConstraints = UriConstraints::create();
        if ((string)$nodeInfo->getSiteNodeName() !== (string)$currentSiteNodeName) {
            $uriConstraints = $this->foo->bar($uriConstraints, $nodeInfo->getSiteNodeName());
        }
        $uriConstraints = $this->contentDimensionProcessor->resolveDimensionUriConstraints($uriConstraints, $nodeAddress->getDimensionSpacePoint());


        if (!empty($this->options['uriSuffix']) && $nodeInfo->hasUriPath()) {
            $uriConstraints = $uriConstraints->withPathSuffix($this->options['uriSuffix']);
        }
        return new ResolveResult($nodeInfo->getUriPath(), $uriConstraints, $nodeInfo->getRouteTags());
    }


    /**
     * @param string $uriPath
     * @param DimensionSpacePoint $dimensionSpacePoint
     * @param NodeName $siteNodeName
     * @return MatchResult
     * @throws NodeNotFoundException | NodeAddressCannotBeSerializedException
     */
    private function matchUriPath(string $uriPath, DimensionSpacePoint $dimensionSpacePoint, NodeName $siteNodeName): MatchResult
    {
        $nodeInfo = $this->documentUriPathFinder->getEnabledBySiteNodeNameUriPathAndDimensionSpacePointHash($siteNodeName, $uriPath, $dimensionSpacePoint->getHash());
        $nodeAddress = new NodeAddress(
            $this->documentUriPathFinder->getLiveContentStreamIdentifier(),
            $dimensionSpacePoint,
            $nodeInfo->getNodeAggregateIdentifier(),
            WorkspaceName::forLive()
        );
        return new MatchResult($nodeAddress->serializeForUri(), $nodeInfo->getRouteTags());
    }

    private function truncateRequestPathAndReturnRemainder(string &$requestPath): string
    {
        $uriPathSegments = explode('/', $requestPath);
        $requestPath = implode('/', $uriPathSegments);
        if (!empty($this->options['uriSuffix'])) {
            $suffixPosition = strpos($requestPath, $this->options['uriSuffix']);
            if ($suffixPosition === false) {
                return '';
            }
            $requestPath = substr($requestPath, 0, $suffixPosition);
        }
        if ($this->splitString === '' || $this->splitString === '/') {
            return '';
        }
        $splitStringPosition = strpos($requestPath, $this->splitString);
        if ($splitStringPosition === false) {
            return '';
        }
        $fullRequestPath = $requestPath;
        $requestPath = substr($requestPath, 0, $splitStringPosition);

        return substr($fullRequestPath, $splitStringPosition);
    }


    private function buildResolveResultFromUri(UriInterface $uri): ResolveResult
    {
        $uriConstraints = UriConstraints::create();
        if (!empty($uri->getScheme())) {
            $uriConstraints = $uriConstraints->withScheme($uri->getScheme());
        }
        if (!empty($uri->getHost())) {
            $uriConstraints = $uriConstraints->withHost($uri->getHost());
        }
        if ($uri->getPort() !== null) {
            $uriConstraints = $uriConstraints->withPort($uri->getPort());
        } elseif (!empty($uri->getScheme())) {
            $uriConstraints = $uriConstraints->withPort($uri->getScheme() === 'https' ? 443 : 80);
        }
        if (!empty($uri->getQuery())) {
            $uriConstraints = $uriConstraints->withQueryString($uri->getQuery());
        }
        if (!empty($uri->getFragment())) {
            $uriConstraints = $uriConstraints->withFragment($uri->getFragment());
        }
        return new ResolveResult($uri->getPath(), $uriConstraints);
    }


    public function setSplitString($splitString): void
    {
        $this->splitString = $splitString;
    }

    public function match(&$routePath)
    {
        throw new \BadMethodCallException('match() is not supported by this Route Part Handler, use "matchWithParameters" instead', 1568287772);
    }

    public function resolve(array &$routeValues)
    {
        throw new \BadMethodCallException('resolve() is not supported by this Route Part Handler, use "resolveWithParameters" instead', 1611600169);
    }
}
