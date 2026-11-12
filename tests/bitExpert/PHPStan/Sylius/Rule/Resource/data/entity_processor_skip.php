<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Sylius\Resource\Model\ResourceInterface;

/**
 * processor: null disables the processor check for this operation: no checks
 * apply, the ResourceBundle defaults the processor.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            processor: null,
        ),
    ],
)]
class EntityProcessorNull implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Non-constant processor arguments cannot be resolved statically and are
 * skipped silently, the same way the factory rule skips them.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            processor: new \stdClass(),
        ),
    ],
)]
class EntityWithNonConstantProcessor implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * An operation attribute without a processor argument is skipped.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
        ),
    ],
)]
class EntityWithoutProcessorArgument implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * A non-resource class is never checked, even with a broken processor.
 */
#[Show(
    path: 'books',
    processor: 'App\Processor\ProcessorClassNotExists',
)]
class EntityProcessorNotAResource
{
    public function getId(): int
    {
        return 1;
    }
}
