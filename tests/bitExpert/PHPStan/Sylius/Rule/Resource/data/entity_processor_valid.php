<?php

declare(strict_types=1);

namespace App\Entity;

use App\Processor\BookProcessor;
use App\Processor\CallableProcessor;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A correct class-string processor: the class exists. The runtime looks it
 * up in the sylius.state_processor service locator, so no method is checked.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            processor: BookProcessor::class,
        ),
    ],
)]
class EntityValidProcessorClass implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A correct callable processor: the method exists and is static.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            processor: [CallableProcessor::class, 'process'],
        ),
    ],
)]
class EntityValidCallableProcessor implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A plain string processor naming an existing function is a valid callable
 * for the runtime: is_callable() is checked before the service locator
 * lookup.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            processor: 'App\Processor\processor_function',
        ),
    ],
)]
class EntityValidFunctionProcessor implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A processor string naming a class that exists as a service-id-like string.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            processor: 'App\Processor\BookProcessor',
        ),
    ],
)]
class EntityValidProcessorString implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}
