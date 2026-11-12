<?php

declare(strict_types=1);

namespace App\Entity;

use App\Factory\BookFactory;
use App\Factory\CallableFactory;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A correct class + factoryMethod configuration: no error.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: BookFactory::class,
            factoryMethod: 'createForAuthor',
        ),
    ],
)]
class EntityValidFactoryMethod implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A correct class-only configuration: the factory provides the default
 * createNew method, so no error.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: CallableFactory::class,
        ),
    ],
)]
class EntityValidFactoryClassOnly implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A correct callable factory: the method is static.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: [CallableFactory::class, 'create'],
        ),
    ],
)]
class EntityValidCallableFactory implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A plain string factory naming an existing function is a valid callable for
 * the runtime: is_callable() is checked before the factory locator lookup,
 * exactly as for providers and processors.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: 'App\Factory\factory_function',
        ),
    ],
)]
class EntityValidFunctionFactory implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}
