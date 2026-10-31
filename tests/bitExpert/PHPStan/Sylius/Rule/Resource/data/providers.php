<?php

declare(strict_types=1);

namespace App\Provider;

use Sylius\Resource\Context\Context;
use Sylius\Resource\Metadata\Operation;
use Sylius\Resource\State\ProviderInterface;

final class BookProvider implements ProviderInterface
{
    public function provide(Operation $operation, Context $context): object|array|null
    {
        return null;
    }
}

final class NotStaticProvider
{
    public function provide(Operation $operation, Context $context): object|array|null
    {
        return null;
    }
}

final class CallableProvider
{
    public static function provide(Operation $operation, Context $context): object|array|null
    {
        return null;
    }
}

final class CallableProviderWrongMethod
{
    public static function createNew(): null
    {
        return null;
    }
}

function provider_function(Operation $operation, Context $context): null
{
    return null;
}
