<?php

declare(strict_types=1);

namespace App\Entity;

use App\Factory\BookFactory;
use App\Factory\CallableFactoryClassNotExists;
use App\Factory\CallableFactoryNotStatic;
use App\Factory\CallableFactoryWrongMethod;
use App\Factory\ClassOnlyFactory;
use App\Factory\FactoryClassNotExists;
use App\Factory\MethodNotExistsFactory;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Factory given as class name with a missing factory class. The factoryMethod
 * is not checked, because the class cannot be resolved.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: FactoryClassNotExists::class,
            factoryMethod: 'createForAuthor',
        ),
    ],
)]
class EntityFactoryClassNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Factory given as class name with a factoryMethod that does not exist on the
 * factory class.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: MethodNotExistsFactory::class,
            factoryMethod: 'methodDoesNotExist',
        ),
    ],
)]
class EntityFactoryMethodNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Factory given as class name without a factoryMethod. The ResourceBundle
 * defaults the method to "createNew", which is missing on this factory class.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: ClassOnlyFactory::class,
        ),
    ],
)]
class EntityFactoryCreateNewNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Callable factory [Class::class, 'method'] with a missing factory class.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: [CallableFactoryClassNotExists::class, 'create'],
        ),
    ],
)]
class EntityCallableFactoryClassNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Callable factory [Class::class, 'method'] with a method that is not static.
 * The runtime invokes the callable as a plain static callable, which fails
 * for a non-static method.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: [CallableFactoryNotStatic::class, 'create'],
        ),
    ],
)]
class EntityCallableFactoryMethodNotStatic implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Callable factory [Class::class, 'method'] with a missing method name.
 */
#[AsResource(
    operations: [
        new Create(
            path: 'books',
            factory: [CallableFactoryWrongMethod::class, 'create'],
        ),
    ],
)]
class EntityCallableFactoryMethodNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}
