<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Sylius\Resource\Model\ResourceInterface;

/**
 * provider: null disables the provider check for this operation: no checks
 * apply, the ResourceBundle defaults the provider.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            provider: null,
        ),
    ],
)]
class EntityProviderNull implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Non-constant provider arguments cannot be resolved statically and are
 * skipped silently, the same way the factory rule skips them.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            provider: new \stdClass(),
        ),
    ],
)]
class EntityWithNonConstantProvider implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * An operation attribute without a provider argument is skipped.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
        ),
    ],
)]
class EntityWithoutProviderArgument implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A non-resource class is never checked, even with a broken provider.
 */
#[Show(
    path: 'books',
    provider: 'App\Provider\ProviderClassNotExists',
)]
class EntityProviderNotAResource
{
    public function getId(): int
    {
        return 1;
    }
}
