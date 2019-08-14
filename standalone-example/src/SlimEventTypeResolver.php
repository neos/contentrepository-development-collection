<?php


namespace Neos\StandaloneCrExample;


use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\Event\EventTypeResolver;

class SlimEventTypeResolver extends EventTypeResolver
{

    public function getEventType(DomainEventInterface $event): string
    {
        return get_class($event);
    }
}
