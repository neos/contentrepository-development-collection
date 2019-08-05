<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Service\Infrastructure\CommandBus;

use League\Tactician\CommandBus;
use League\Tactician\Exception\MissingHandlerException;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\Locator\CallableLocator;
use League\Tactician\Handler\Locator\HandlerLocator;
use League\Tactician\Handler\MethodNameInflector\HandleClassNameWithoutSuffixInflector;
use Neos\EventSourcedContentRepository\CommandHandlerInterface;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\EventSourcing\EventListener\EventListenerInterface;
use Neos\EventSourcing\EventStore\RawEvent;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Neos\Exception as NeosException;
use Neos\Neos\Service\DataSource\DataSourceInterface;
use Neos\Utility\Arrays;

/**
 * @Flow\Scope("singleton")
 */
final class CommandHandlerLocator implements HandlerLocator
{

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\InjectConfiguration(path="commandHandlerMap")
     * @var array
     */
    protected $customCommandHandlerMap;

    /**
     * @var array
     */
    private $commandHandlerMap;

    public function initializeObject(): void
    {
        $commandHandlerMap = self::getCommandHandlerMap($this->objectManager);
        $this->commandHandlerMap = Arrays::arrayMergeRecursiveOverrule($commandHandlerMap, $this->customCommandHandlerMap);
    }

    /**
     * Retrieves the handler for a specified command
     *
     * @param string $commandName
     *
     * @return object
     * @throws MissingHandlerException
     */
    public function getHandlerForCommand($commandName)
    {
        if (!array_key_exists($commandName, $this->commandHandlerMap)) {
            throw new MissingHandlerException(sprintf('Could not find handler for command "%s"', $commandName), 1565010791);
        }
        return $this->objectManager->get($this->commandHandlerMap[$commandName]);
    }

    /**
     * @param ObjectManagerInterface $objectManager
     * @return array
     * @Flow\CompileStatic
     * @throws \ReflectionException
     */
    public static function getCommandHandlerMap($objectManager): array
    {
        $commandToHandlerMap = [];
        /** @var ReflectionService $reflectionService */
        $reflectionService = $objectManager->get(ReflectionService::class);
        foreach ($reflectionService->getAllImplementationClassNamesForInterface(CommandHandlerInterface::class) as $handlerClassName) {
            foreach (get_class_methods($handlerClassName) as $handlerMethodNames) {
                preg_match('/^handle[A-Z].*$/', $handlerMethodNames, $matches);
                if (!isset($matches[0])) {
                    continue;
                }
                $parameters = array_values($reflectionService->getMethodParameters($handlerClassName, $handlerMethodNames));

                if (!isset($parameters[0])) {
                    throw new \RuntimeException(sprintf('Invalid handler in %s::%s the method signature is wrong, must accept a command argument', $handlerClassName, $handlerMethodNames), 1565009827);
                }
                if (isset($parameters[1])) {
                    throw new \RuntimeException(sprintf('Invalid handler in %s::%s the method signature is wrong, must accept exactly one argument', $handlerClassName, $handlerMethodNames), 1565010333);
                }
                $commandClassName = $parameters[0]['class'];
                if ($commandClassName === null) {
                    throw new \RuntimeException(sprintf('Invalid handler in %s::%s the method signature is wrong, must accept a (command) object as argument', $handlerClassName, $handlerMethodNames), 1565010430);
                }
                $expectedMethodName = 'handle' . (new \ReflectionClass($commandClassName))->getShortName();
                if ($expectedMethodName !== $handlerMethodNames) {
                    throw new \RuntimeException(sprintf('Invalid handler in %s::%s the method name is expected to be "%s"', $handlerClassName, $handlerMethodNames, $expectedMethodName), 1565010516);
                }

                $commandToHandlerMap[$commandClassName] = $handlerClassName;
            }
        }
        return $commandToHandlerMap;
    }
}
