<?php

/*
 * This file is part of the phpstan-sylius package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace bitExpert\PHPStan\Sylius\Collector\Grid\Field;

use PHPStan\DependencyInjection\Container;

class FieldRegistryFactory
{
    public const TYPE_DESCRIPTOR_TAG = 'phpstan.sylius.grid.field';

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function createRegistry(): FieldRegistry
    {
        /** @var FieldNode[] $fields */
        $fields = $this->container->getServicesByTag(self::TYPE_DESCRIPTOR_TAG);

        return new DefaultFieldRegistry($fields);
    }
}
