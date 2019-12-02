<?php


namespace Neos\EventSourcedContentRepository\Standalone\DependencyInjection\Overrides;


use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\Event\EventTypeResolver;

class SlimEventTypeResolver extends EventTypeResolver
{

    public function getEventType(DomainEventInterface $event): string
    {
        return get_class($event);
    }

    public function getEventTypeByClassName(string $className): string
    {
        return $className;
    }

    public function getEventClassNameByType(string $eventType): string
    {
        return $eventType;
    }
}
