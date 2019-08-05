<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Service\Infrastructure\CommandBus;

use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\MethodNameInflector\HandleClassNameWithoutSuffixInflector;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
final class CommandBusFactory
{

    /**
     * @Flow\Inject(lazy=false)
     * @var CommandHandlerLocator
     */
    protected $commandHandlerLocator;

    /**
     * @return CommandBus
     */
    public function get(): CommandBus
    {
        $handlerMiddleware = new CommandHandlerMiddleware(
            new ClassNameExtractor(),
            $this->commandHandlerLocator,
            new HandleClassNameWithoutSuffixInflector()
        );
        return new CommandBus([$handlerMiddleware]);
    }
}
