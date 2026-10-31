<?php

declare(strict_types=1);

namespace App\Entity;

use App\Processor\CallableProcessor;
use App\Processor\CallableProcessorWrongMethod;
use App\Processor\NotStaticProcessor;
use App\Processor\ProcessorClassNotExists;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Show;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Processor given as a class name that does not exist. The method check is
 * not applied, because the class cannot be resolved.
 */
#[AsResource(
    operations: [
        new Index(
            path: 'books',
            processor: ProcessorClassNotExists::class,
        ),
    ],
)]
class EntityProcessorClassNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Callable processor [Class::class, 'method'] with a method that does not
 * exist on the processor class.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            processor: [CallableProcessorWrongMethod::class, 'process'],
        ),
    ],
)]
class EntityProcessorMethodNotExists implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}

/**
 * Callable processor [Class::class, 'method'] with a method that is not
 * static. The runtime invokes the callable as a plain static callable, which
 * fails for a non-static method.
 */
#[AsResource(
    operations: [
        new Show(
            path: 'books',
            processor: [NotStaticProcessor::class, 'process'],
        ),
    ],
)]
class EntityProcessorMethodNotStatic implements ResourceInterface
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
    processor: [CallableProcessor::class, 'methodDoesNotExist'],
)]
class EntityWithStandaloneShowProcessorAttribute implements ResourceInterface
{
    public function getId(): int
    {
        return 1;
    }
}
