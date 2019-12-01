<?php

namespace Neos\StandaloneCrExample;

use Doctrine\DBAL\Connection;
use Neos\EventSourcing\EventListener\EventListenerInterface;
use Neos\EventSourcing\EventListener\EventListenerInvoker;
use Neos\EventSourcing\EventStore\EventStore;

class SlimEventListenerInvoker extends EventListenerInvoker {

    protected $eventStore;

    public function __construct(EventStore $eventStore, Connection $connection)
    {
        $this->eventStore = $eventStore;
        $this->connection = $connection;
    }

    public function getEventStoreForEventListener(EventListenerInterface $listener): EventStore
    {
        return $this->eventStore;
    }
}
