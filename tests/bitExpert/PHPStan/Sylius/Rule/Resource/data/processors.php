<?php

declare(strict_types=1);

namespace App\Processor;

use Sylius\Resource\Context\Context;
use Sylius\Resource\Metadata\Operation;
use Sylius\Resource\State\ProcessorInterface;

final class BookProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, Context $context): mixed
    {
        return null;
    }
}

final class NotStaticProcessor
{
    public function process(mixed $data, Operation $operation, Context $context): mixed
    {
        return null;
    }
}

final class CallableProcessor
{
    public static function process(mixed $data, Operation $operation, Context $context): mixed
    {
        return null;
    }
}

final class CallableProcessorWrongMethod
{
    public static function createNew(): null
    {
        return null;
    }
}

function processor_function(mixed $data, Operation $operation, Context $context): null
{
    return null;
}
