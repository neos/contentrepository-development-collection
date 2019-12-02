<?php

namespace Neos\EventSourcedContentRepository\Standalone\DependencyInjection;

use Neos\EventSourcedContentRepository\Standalone\Configuration\ContentRepositoryConfiguration;
use Neos\EventSourcedContentRepository\Standalone\DependencyInjection\Overrides\SlimEventListenerInvoker;
use Neos\EventSourcedContentRepository\Standalone\DependencyInjection\Overrides\SlimEventTypeResolver;
use Neos\EventSourcing\Event\EventTypeResolver;
use Neos\EventSourcing\EventListener\EventListenerInvoker;
use Neos\EventSourcing\EventListener\EventListenerLocator;
use Neos\EventSourcing\EventStore\EventListenerTrigger\EventListenerTrigger;
use Neos\EventSourcing\EventStore\EventNormalizer;
use Neos\EventSourcing\EventStore\EventStore;
use Neos\EventSourcing\EventStore\Storage\Doctrine\Factory\ConnectionFactory;
use Neos\EventSourcing\EventStore\Storage\EventStorageInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class EventSourcingContainerConfigurer
{
    public static function configure(ContainerBuilder $containerBuilder, ContentRepositoryConfiguration $contentRepositoryConfiguration)
    {
        $containerBuilder->register(EventStore::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventStore'])
            ->addArgument(new Reference(EventStorageInterface::class))
            ->addArgument(new Reference(EventTypeResolver::class))
            ->addArgument(new Reference(EventNormalizer::class))
            ->addArgument(new Reference(EventListenerTrigger::class))
            ->setPublic(true);

        $containerBuilder
            ->register(ConnectionFactory::class)
            ->setFactory([EventSourcingFactories::class, 'buildConnectionFactory']);

        $containerBuilder->register(EventStorageInterface::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventStorage'])
            ->addArgument(new Reference(ConnectionFactory::class))
            ->addArgument(new Reference(EventNormalizer::class))
            ->addArgument('%contentRepositoryConfiguration%');

        $containerBuilder->register(EventTypeResolver::class, SlimEventTypeResolver::class);

        $containerBuilder->register(EventNormalizer::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventNormalizer'])
            ->addArgument(new Reference(EventTypeResolver::class));

        $containerBuilder->register(EventListenerTrigger::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventListenerTrigger'])
            ->addArgument(new Reference(EventListenerLocator::class));

        $containerBuilder->register(EventListenerLocator::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventListenerLocator']);

        $containerBuilder->register(EventListenerInvoker::class, SlimEventListenerInvoker::class)
            ->setFactory([EventSourcingFactories::class, 'buildEventListenerInvoker'])
            ->addArgument(new Reference(EventStore::class))
            ->addArgument(new Reference(ConnectionFactory::class))
            ->addArgument('%contentRepositoryConfiguration%')
            ->setPublic(true);
    }
}
