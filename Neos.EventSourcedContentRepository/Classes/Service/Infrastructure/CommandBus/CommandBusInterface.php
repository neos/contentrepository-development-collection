<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Service\Infrastructure\CommandBus;

interface CommandBusInterface
{
    public function handle($command);
}
