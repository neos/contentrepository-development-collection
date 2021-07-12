<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\SiteResolver;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Dto\UriConstraints;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;

final class DefaultSiteResolver implements RoutingSiteResolverInterface
{
    private DomainRepository $domainRepository;
    private SiteRepository $siteRepository;

    /**
     * @var NodeName[] (indexed by the corresponding host)
     */
    private array $siteNodeNameRuntimeCache = [];

    public function __construct(DomainRepository $domainRepository, SiteRepository $siteRepository)
    {
        $this->domainRepository = $domainRepository;
        $this->siteRepository = $siteRepository;
    }

    public function getCurrentSiteNode(RouteParameters $routeParameters): NodeName
    {
        // TODO fallback if "requestUriHost" is not set (e.g. use site if there is only one active OR: exception)
        $host = (string)$routeParameters->getValue('requestUriHost');
        if (!isset($this->siteNodeNameRuntimeCache[$host])) {
            $site = null;
            if (!empty($host)) {
                $activeDomain = $this->domainRepository->findOneByHost($host, true);
                if ($activeDomain !== null) {
                    $site = $activeDomain->getSite();
                }
            }
            if ($site === null) {
                $site = $this->siteRepository->findFirstOnline();
            }
            $this->siteNodeNameRuntimeCache[$host] = NodeName::fromString($site->getNodeName());
        }
        return $this->siteNodeNameRuntimeCache[$host];
    }

    public function buildUriConstraintsForSite(RouteParameters $routeParameters, NodeName $targetSiteNodeName): UriConstraints
    {
        $uriConstraints = UriConstraints::create();
        $cS = $this->getCurrentSiteNode($routeParameters);
        if ((string)$cS === (string)$targetSiteNodeName) {
            return $uriConstraints;
        }
        /** @var Site $site */
        foreach ($this->siteRepository->findOnline() as $site) {
            if ($site->getNodeName() === (string)$targetSiteNodeName) {
                $domain = $site->getPrimaryDomain();
                if ($domain === null) {
                    return UriConstraints::create();
                }
                return $this->applyDomainToUriConstraints($uriConstraints, $domain);
            }
        }
    }

    private function applyDomainToUriConstraints(UriConstraints $uriConstraints, Domain $domain): UriConstraints
    {
        $uriConstraints = $uriConstraints->withHost($domain->getHostname());
        if (!empty($domain->getScheme())) {
            $uriConstraints = $uriConstraints->withScheme($domain->getScheme());
        }
        if (!empty($domain->getPort())) {
            $uriConstraints = $uriConstraints->withPort($domain->getPort());
        }
        return $uriConstraints;
    }
}
