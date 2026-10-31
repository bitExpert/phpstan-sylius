<?php

declare(strict_types=1);

namespace App\Entity;

use App\Provider\CallableProvider;
use App\Provider\CallableProviderWrongMethod;
use App\Provider\NotStaticProvider;
use App\Provider\ProviderClassNotExists;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Provider given as a class name that does not exist. The factoryMethod-style
 * method check is not applied, because the class cannot be resolved.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            provider: ProviderClassNotExists::class,
        ),
    ],
)]
class EntityProviderClassNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Callable provider [Class::class, 'method'] with a method that does not
 * exist on the provider class.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            provider: [CallableProviderWrongMethod::class, 'provide'],
        ),
    ],
)]
class EntityProviderMethodNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Callable provider [Class::class, 'method'] with a method that is not
 * static. The runtime invokes the callable as a plain static callable, which
 * fails for a non-static method.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            provider: [NotStaticProvider::class, 'provide'],
        ),
    ],
)]
class EntityProviderMethodNotStatic implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A Show attribute declared directly on the resource class (not nested in
 * AsResource) is validated as well.
 */
#[Show(
    path: 'books',
    provider: [CallableProvider::class, 'methodDoesNotExist'],
)]
class EntityWithStandaloneShowAttribute implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}
