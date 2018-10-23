<?php
namespace Neos\EventSourcedNeosAdjustments\Fusion;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\ValueObject\NodeAggregateIdentifier;
use Neos\EventSourcedNeosAdjustments\Domain\Context\Content\NodeAddressFactory;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Neos\Domain\Exception;
use Neos\Neos\Service\LinkingService;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

/**
 * A Fusion Object that converts link references in the format "<type>://<UUID>" to proper URIs
 *
 * Right now node://<UUID> and asset://<UUID> are supported URI schemes.
 *
 * Usage::
 *
 *   someTextProperty.@process.1 = Neos.Neos:ConvertUris
 *
 * The optional property ``forceConversion`` can be used to have the links converted even when not
 * rendering the live workspace. This is used for links that are not inline editable (for
 * example links on images)::
 *
 *   someTextProperty.@process.1 = Neos.Neos:ConvertUris {
 *     forceConversion = true
 *   }
 *
 * The optional property ``externalLinkTarget`` can be modified to disable or change the target attribute of the
 * link tag for links to external targets::
 *
 *   prototype(Neos.Neos:ConvertUris) {
 *     externalLinkTarget = '_blank'
 *     resourceLinkTarget = '_blank'
 *   }
 *
 * The optional property ``absolute`` can be used to convert node uris to absolute links::
 *
 *   someTextProperty.@process.1 = Neos.Neos:ConvertUris {
 *     absolute = true
 *   }
 */
class ConvertUrisImplementation extends AbstractFusionObject
{
    /**
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * @Flow\Inject
     * @var NodeAddressFactory
     */
    protected $nodeAddressFactory;

    /**
     * Convert URIs matching a supported scheme with generated URIs
     *
     * If the workspace of the current node context is not live, no replacement will be done unless forceConversion is
     * set. This is needed to show the editable links with metadata in the content module.
     *
     * @return string
     * @throws Exception
     */
    public function evaluate()
    {
        $text = $this->fusionValue('value');

        if ($text === '' || $text === null) {
            return '';
        }

        if (!is_string($text)) {
            throw new Exception(sprintf('Only strings can be processed by this Fusion object, given: "%s".', gettype($text)), 1382624080);
        }

        /* @var $node NodeInterface */
        $node = $this->fusionValue('node');

        if (!$node instanceof NodeInterface) {
            throw new Exception(sprintf('The current node must be an instance of NodeInterface, given: "%s".', gettype($text)), 1382624087);
        }

        $nodeAddress = $this->nodeAddressFactory->createFromNode($node);

        if (!$nodeAddress->isInLiveWorkspace() && !($this->fusionValue('forceConversion'))) {
            return $text;
        }

        $unresolvedUris = [];
        $absolute = $this->fusionValue('absolute');

        $processedContent = preg_replace_callback(LinkingService::PATTERN_SUPPORTED_URIS, function (array $matches) use (&$unresolvedUris, $absolute, $nodeAddress) {
            switch ($matches[1]) {
                case 'node':
                    $nodeAddress = $this->nodeAddressFactory->adjustWithNodeAggregateIdentifier($nodeAddress, new NodeAggregateIdentifier($matches[2]));
                    $uriBuilder = new UriBuilder();
                    $uriBuilder->setRequest($this->runtime->getControllerContext()->getRequest());
                    $uriBuilder->setCreateAbsoluteUri($absolute);

                    $resolvedUri = $uriBuilder->uriFor(
                        'show',
                        [
                            'node' => $nodeAddress
                        ],
                        'Frontend\Node',
                        'Neos.Neos'
                    );

                    $this->runtime->addCacheTag('node', $matches[2]);
                    break;
                case 'asset':
                    $resolvedUri = $this->linkingService->resolveAssetUri($matches[0]);
                    $this->runtime->addCacheTag('asset', $matches[2]);
                    break;
                default:
                    $resolvedUri = null;
            }

            if ($resolvedUri === null) {
                $unresolvedUris[] = $matches[0];
                return $matches[0];
            }

            return $resolvedUri;
        }, $text);

        if ($unresolvedUris !== []) {
            $processedContent = preg_replace('/<a[^>]* href="(node|asset):\/\/[^"]+"[^>]*>(.*?)<\/a>/', '$2', $processedContent);
            $processedContent = preg_replace(LinkingService::PATTERN_SUPPORTED_URIS, '', $processedContent);
        }

        $processedContent = $this->replaceLinkTargets($processedContent);

        return $processedContent;
    }

    /**
     * Replace the target attribute of link tags in processedContent with the target
     * specified by externalLinkTarget and resourceLinkTarget options.
     * Additionally set rel="noopener" for links with target="_blank".
     *
     * @param string $processedContent
     * @return string
     */
    protected function replaceLinkTargets($processedContent)
    {
        $noOpenerString = $this->fusionValue('setNoOpener') ? ' rel="noopener"' : '';
        $externalLinkTarget = trim($this->fusionValue('externalLinkTarget'));
        $resourceLinkTarget = trim($this->fusionValue('resourceLinkTarget'));
        if ($externalLinkTarget === '' && $resourceLinkTarget === '') {
            return $processedContent;
        }
        $controllerContext = $this->runtime->getControllerContext();
        $host = $controllerContext->getRequest()->getHttpRequest()->getUri()->getHost();
        $processedContent = preg_replace_callback(
            '~<a.*?href="(.*?)".*?>~i',
            function ($matches) use ($externalLinkTarget, $resourceLinkTarget, $host, $noOpenerString) {
                list($linkText, $linkHref) = $matches;
                $uriHost = parse_url($linkHref, PHP_URL_HOST);
                $target = null;
                if ($externalLinkTarget !== '' && is_string($uriHost) && $uriHost !== $host) {
                    $target = $externalLinkTarget;
                }
                if ($resourceLinkTarget !== '' && strpos($linkHref, '_Resources') !== false) {
                    $target = $resourceLinkTarget;
                }
                if ($target === null) {
                    return $linkText;
                }
                if (preg_match_all('~target="(.*?)~i', $linkText, $targetMatches)) {
                    return preg_replace('/target=".*?"/', sprintf('target="%s"%s', $target, $target === '_blank' ? $noOpenerString : ''), $linkText);
                }
                return str_replace('<a', sprintf('<a target="%s"%s', $target, $target === '_blank' ? $noOpenerString : ''), $linkText);
            },
            $processedContent
        );
        return $processedContent;
    }
}
