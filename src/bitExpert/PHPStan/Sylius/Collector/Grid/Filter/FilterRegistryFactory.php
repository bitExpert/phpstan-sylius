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

namespace bitExpert\PHPStan\Sylius\Collector\Grid\Filter;

use PHPStan\DependencyInjection\Container;

class FilterRegistryFactory
{
    public const TYPE_DESCRIPTOR_TAG = 'phpstan.sylius.grid.filter';

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function createRegistry(): FilterRegistry
    {
        /** @var FilterNode[] $filters */
        $filters = $this->container->getServicesByTag(self::TYPE_DESCRIPTOR_TAG);

        return new DefaultFilterRegistry($filters);
    }
}
