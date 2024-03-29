<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Domain\Service;

/*
 * This file is part of the Neos.EventSourcedNeosAdjustments package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Exception\InvalidShortcutException;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Projection\DocumentUriPathFinder;
use Neos\EventSourcedNeosAdjustments\EventSourcedRouting\ValueObject\DocumentNodeInfo;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Neos\Controller\Exception\NodeNotFoundException;
use Psr\Http\Message\UriInterface;

/**
 * Can resolve the target for a given shortcut.
 * Used for Neos Routing ({@see EventSourcedFrontendNodeRoutePartHandler}),
 * and redirects to a shortcut target when visiting the shortcut itself.
 *
 * @Flow\Scope("singleton")
 */
class NodeShortcutResolver
{
    private DocumentUriPathFinder $documentUriPathFinder;

    private AssetRepository $assetRepository;

    private ResourceManager $resourceManager;

    public function __construct(
        DocumentUriPathFinder $documentUriPathFinder,
        AssetRepository $assetRepository,
        ResourceManager $resourceManager
    ) {
        $this->documentUriPathFinder = $documentUriPathFinder;
        $this->assetRepository = $assetRepository;
        $this->resourceManager = $resourceManager;
    }

    /**
     * "adapter" for {@see resolveNode} when working with NodeAddresses.
     * Note: The ContentStreamIdentifier is not required for this service,
     * because it is only covering the live workspace
     *
     * @param NodeAddress $nodeAddress
     * @return NodeAddress|UriInterface NodeAddress is returned if we want to link to another node
     * (i.e. node is NOT a shortcut node; or target is a node);
     * or UriInterface for links to fixed URLs (Asset URLs or external URLs)
     * @throws InvalidShortcutException
     * @throws NodeNotFoundException
     */
    public function resolveShortcutTarget(NodeAddress $nodeAddress)
    {
        $documentNodeInfo = $this->documentUriPathFinder->getByIdAndDimensionSpacePointHash(
            $nodeAddress->nodeAggregateIdentifier,
            $nodeAddress->dimensionSpacePoint->hash
        );
        $resolvedTarget = $this->resolveNode($documentNodeInfo);
        if ($resolvedTarget instanceof UriInterface) {
            return $resolvedTarget;
        }
        if ($resolvedTarget === $documentNodeInfo) {
            return $nodeAddress;
        }
        return $nodeAddress->withNodeAggregateIdentifier($documentNodeInfo->getNodeAggregateIdentifier());
    }

    /**
     * This method is used during routing (when creating URLs), to directly generate URLs to the shortcut TARGET,
     * if linking to a shortcut.
     * Note: The ContentStreamIdentifier is not required for this service,
     * because it is only covering the live workspace
     *
     * @param DocumentNodeInfo $documentNodeInfo
     * @return DocumentNodeInfo|UriInterface DocumentNodeInfo is returned if we want to link to another node
     * (i.e. node is NOT a shortcut node; or target is a node);
     * or UriInterface for links to fixed URLs (Asset URLs or external URLs)
     * @throws InvalidShortcutException
     */
    public function resolveNode(DocumentNodeInfo $documentNodeInfo)
    {
        $shortcutRecursionLevel = 0;
        while ($documentNodeInfo->isShortcut()) {
            if (++ $shortcutRecursionLevel > 50) {
                throw new InvalidShortcutException(sprintf(
                    'Shortcut recursion level reached after %d levels',
                    $shortcutRecursionLevel
                ), 1599035282);
            }
            switch ($documentNodeInfo->getShortcutMode()) {
                case 'parentNode':
                    try {
                        $documentNodeInfo = $this->documentUriPathFinder->getParentNode($documentNodeInfo);
                    } catch (NodeNotFoundException $e) {
                        throw new InvalidShortcutException(sprintf(
                            'Shortcut Node "%s" points to a non-existing parent node "%s"',
                            $documentNodeInfo,
                            $documentNodeInfo->getNodeAggregateIdentifier()
                        ), 1599669406, $e);
                    }
                    if ($documentNodeInfo->isDisabled()) {
                        throw new InvalidShortcutException(sprintf(
                            'Shortcut Node "%s" points to disabled parent node "%s"',
                            $documentNodeInfo,
                            $documentNodeInfo->getNodeAggregateIdentifier()
                        ), 1599664517);
                    }
                    continue 2;
                case 'firstChildNode':
                    try {
                        $documentNodeInfo = $this->documentUriPathFinder->getFirstEnabledChildNode(
                            $documentNodeInfo->getNodeAggregateIdentifier(),
                            $documentNodeInfo->getDimensionSpacePointHash()
                        );
                    } catch (\Exception $e) {
                        throw new InvalidShortcutException(sprintf(
                            'Failed to fetch firstChildNode in Node "%s": %s',
                            $documentNodeInfo,
                            $e->getMessage()
                        ), 1599043861, $e);
                    }
                    continue 2;
                case 'selectedTarget':
                    try {
                        $targetUri = $documentNodeInfo->getShortcutTargetUri();
                    } catch (\Exception $e) {
                        throw new InvalidShortcutException(sprintf(
                            'Invalid shortcut target in Node "%s": %s',
                            $documentNodeInfo,
                            $e->getMessage()
                        ), 1599043489, $e);
                    }
                    if ($targetUri->getScheme() === 'node') {
                        $targetNodeAggregateIdentifier = NodeAggregateIdentifier::fromString($targetUri->getHost());
                        try {
                            $documentNodeInfo = $this->documentUriPathFinder->getByIdAndDimensionSpacePointHash(
                                $targetNodeAggregateIdentifier,
                                $documentNodeInfo->getDimensionSpacePointHash()
                            );
                        } catch (\Exception $e) {
                            throw new InvalidShortcutException(sprintf(
                                'Failed to load selectedTarget node in Node "%s": %s',
                                $documentNodeInfo,
                                $e->getMessage()
                            ), 1599043803, $e);
                        }
                        if ($documentNodeInfo->isDisabled()) {
                            throw new InvalidShortcutException(sprintf(
                                'Shortcut target in Node "%s" points to disabled node "%s"',
                                $documentNodeInfo,
                                $documentNodeInfo->getNodeAggregateIdentifier()
                            ), 1599664423);
                        }
                        continue 2;
                    }
                    if ($targetUri->getScheme() === 'asset') {
                        $asset = $this->assetRepository->findByIdentifier($targetUri->getHost());
                        if (!$asset instanceof AssetInterface) {
                            throw new InvalidShortcutException(sprintf(
                                'Failed to load selectedTarget asset in Node "%s", probably it was deleted',
                                $documentNodeInfo
                            ), 1599314109);
                        }
                        $assetUri = $this->resourceManager->getPublicPersistentResourceUri($asset->getResource());
                        if (!is_string($assetUri)) {
                            throw new InvalidShortcutException(sprintf(
                                'Failed to resolve asset URI in Node "%s", probably it was deleted',
                                $documentNodeInfo
                            ), 1599314203);
                        }
                        return new Uri($assetUri);
                    }
                    return $targetUri;
                default:
                    throw new InvalidShortcutException(sprintf(
                        'Unsupported shortcut mode "%s" in Node "%s"',
                        $documentNodeInfo->getShortcutMode(),
                        $documentNodeInfo
                    ), 1598194032);
            }
        }
        return $documentNodeInfo;
    }
}
