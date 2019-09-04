<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Service\Infrastructure\CommandBus;

use Neos\EventSourcedContentRepository\CommandHandlerInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Service\Infrastructure\CommandBus\Exception\MissingHandlerException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\Arrays;

/**
 * @Flow\Scope("singleton")
 */
final class CommandBus
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
    private $cachedCommandHandlerMap;

    /**
     * @var CommandHandlerInterface[]
     */
    private $cachedCommandHandlers = [];

    public function handle($command): CommandResult
    {
        $commandHandler = $this->resolveCommandHandler($command);
        $methodName = $this->resolveCommandHandlerMethodName($command);
        return $commandHandler->{$methodName}($command);
    }

    public function handleBlocking($command): void
    {
        $this->handle($command)->blockUntilProjectionsAreUpToDate();
    }

    private function resolveCommandHandler($command): CommandHandlerInterface
    {
        if ($this->cachedCommandHandlerMap === null) {
            $commandHandlerMap = self::getCommandHandlerMap($this->objectManager);
            $this->cachedCommandHandlerMap = Arrays::arrayMergeRecursiveOverrule($commandHandlerMap, $this->customCommandHandlerMap);
        }
        $commandClassName = get_class($command);
        if (!\array_key_exists($commandClassName, $this->cachedCommandHandlerMap)) {
            throw new MissingHandlerException(sprintf('No handler was found for command "%s"', $commandClassName), 1567528583);
        }
        $commandHandlerClassName = $this->cachedCommandHandlerMap[$commandClassName];
        if (!\array_key_exists($commandHandlerClassName, $this->cachedCommandHandlers)) {
            $this->cachedCommandHandlers[$commandHandlerClassName] = $this->objectManager->get($commandHandlerClassName);
        }
        return $this->cachedCommandHandlers[$commandHandlerClassName];
    }

    private function resolveCommandHandlerMethodName($command): string
    {
        $commandClassName = get_class($command);
        return 'handle' . substr($commandClassName, strrpos($commandClassName, '\\') + 1);
    }

    /**
     * @param ObjectManagerInterface $objectManager
     * @return array
     * @Flow\CompileStatic
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
                try {
                    $expectedMethodName = 'handle' . (new \ReflectionClass($commandClassName))->getShortName();
                } catch (\ReflectionException $exception) {
                    throw new \RuntimeException(sprintf('Failed to extract short class name for command class "%s": %s', $commandClassName, $exception->getMessage()), 1567528274, $exception);
                }
                if ($expectedMethodName !== $handlerMethodNames) {
                    throw new \RuntimeException(sprintf('Invalid handler in %s::%s the method name is expected to be "%s"', $handlerClassName, $handlerMethodNames, $expectedMethodName), 1565010516);
                }

                $commandToHandlerMap[$commandClassName] = $handlerClassName;
            }
        }
        return $commandToHandlerMap;
    }
}
