<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\EventSourcedRouting\Foo;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;

interface FooInterface
{
    public function getCurrentSiteNode(RouteParameters $routeParameters): NodeName;
}
