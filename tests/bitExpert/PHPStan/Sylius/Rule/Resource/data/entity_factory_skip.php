<?php

declare(strict_types=1);

namespace App\Entity;

use App\Factory\BookFactory;
use App\Factory\CallableFactory;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Model\ResourceInterface;

/**
 * factory: false disables the factory for this operation: no checks apply.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: false,
        ),
    ],
)]
class EntityFactoryDisabled implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Non-constant factory arguments cannot be resolved statically and are
 * skipped silently. Before this guard, calling Type::getValue() on them
 * raised an internal error and aborted the analysis.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: new \stdClass(),
        ),
    ],
)]
class EntityWithNonConstantFactory implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Non-constant factoryMethod arguments are skipped silently.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: BookFactory::class,
            factoryMethod: new \stdClass(),
        ),
    ],
)]
class EntityWithNonConstantFactoryMethod implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A Create attribute declared directly on the resource class (not nested in
 * AsResource) is validated as well.
 */
#[Create(
    path: 'books',
    factory: CallableFactory::class,
    factoryMethod: 'methodDoesNotExist',
)]
class EntityWithStandaloneCreateAttribute implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}
