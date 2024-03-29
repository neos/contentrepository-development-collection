<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\LegacyApi\LegacyNodeInterfaceApi;

use Neos\EventSourcedContentRepository\ContentAccess\NodeAccessorManager;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddressFactory;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\EventSourcedContentRepository\LegacyApi\Logging\LegacyLoggerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;

/**
 * @Flow\Aspect
 * @Flow\Introduce("within(Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface)", interfaceName="Neos\EventSourcedContentRepository\LegacyApi\LegacyNodeInterfaceApi\LegacyNodeInterfaceApi")
 */
class ImplementLegacyApiInNodeBasedReadModelInterfaceAspect
{
    /**
     * @Flow\Inject
     * @var LegacyLoggerInterface
     */
    protected $legacyLogger;

    /**
     * @Flow\Inject
     * @var NodeAccessorManager
     */
    protected $nodeAccessorManager;

    /**
     * @Flow\Inject
     * @var NodeAddressFactory
     */
    protected $nodeAddressFactory;

    /**
     * @Flow\Around("within(Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface) && method(.*->getIdentifier())")
     */
    public function getIdentifier(\Neos\Flow\Aop\JoinPointInterface $joinPoint): string
    {
        $this->legacyLogger->info(
            'NodeInterface.getIdentifier() called',
            LogEnvironment::fromMethodName(LegacyNodeInterfaceApi::class . '::getIdentifier')
        );

        /** @var NodeInterface $traversableNode */
        $traversableNode = $joinPoint->getProxy();
        return $traversableNode->getNodeAggregateIdentifier()->jsonSerialize();
    }

    /**
     * @Flow\Around("within(Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface) && method(.*->getDepth())")
     */
    public function getDepth(\Neos\Flow\Aop\JoinPointInterface $joinPoint): int
    {
        $this->legacyLogger->info(
            'NodeInterface.getDepth() called',
            LogEnvironment::fromMethodName(LegacyNodeInterfaceApi::class . '::getDepth')
        );

        /** @var NodeInterface $traversableNode */
        $traversableNode = $joinPoint->getProxy();
        $nodeAccessor = $this->nodeAccessorManager->accessorFor(
            $traversableNode->getContentStreamIdentifier(),
            $traversableNode->getDimensionSpacePoint(),
            $traversableNode->getVisibilityConstraints()
        );
        return $nodeAccessor->findNodePath($traversableNode)->getDepth();
    }

    /**
     * @Flow\Around("within(Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface) && method(.*->getHiddenBeforeDateTime())")
     */
    public function getHiddenBeforeDateTime(\Neos\Flow\Aop\JoinPointInterface $joinPoint): ?\DateTimeInterface
    {
        $this->legacyLogger->info(
            'NodeInterface.getHiddenBeforeDateTime() called (not supported)',
            LogEnvironment::fromMethodName(LegacyNodeInterfaceApi::class . '::getHiddenBeforeDateTime')
        );
        // not supported
        return null;
    }

    /**
     * @Flow\Around("within(Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface) && method(.*->getHiddenAfterDateTime())")
     */
    public function getHiddenAfterDateTime(\Neos\Flow\Aop\JoinPointInterface $joinPoint): ?\DateTimeInterface
    {
        $this->legacyLogger->info(
            'NodeInterface.getHiddenAfterDateTime() called (not supported)',
            LogEnvironment::fromMethodName(LegacyNodeInterfaceApi::class . '::getHiddenAfterDateTime')
        );
        // not supported
        return null;
    }

    /**
     * @Flow\Around("within(Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeInterface) && method(.*->getContextPath())")
     */
    public function getContextPath(\Neos\Flow\Aop\JoinPointInterface $joinPoint): string
    {
        $this->legacyLogger->info(
            'NodeInterface.getContextPath() called',
            LogEnvironment::fromMethodName(LegacyNodeInterfaceApi::class . '::getContextPath')
        );

        /** @var NodeInterface $node */
        $node = $joinPoint->getProxy();
        return $this->nodeAddressFactory->createFromNode($node)->serializeForUri();
    }
}
