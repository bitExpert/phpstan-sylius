<?php

declare(strict_types=1);

namespace App\Entity;

use App\Provider\BookProvider;
use App\Provider\CallableProvider;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A correct class-string provider: the class exists. The runtime looks it up
 * in the sylius.state_provider service locator, so no method is checked.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            provider: BookProvider::class,
        ),
    ],
)]
class EntityValidProviderClass implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A correct callable provider: the method exists and is static.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            provider: [CallableProvider::class, 'provide'],
        ),
    ],
)]
class EntityValidCallableProvider implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A plain string provider naming an existing function is a valid callable for
 * the runtime: is_callable() is checked before the service locator lookup.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            provider: 'App\Provider\provider_function',
        ),
    ],
)]
class EntityValidFunctionProvider implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A provider string naming a class that exists as a service-id-like string.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            provider: 'App\Provider\BookProvider',
        ),
    ],
)]
class EntityValidProviderString implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}
