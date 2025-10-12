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

namespace bitExpert\PHPStan\Sylius\Rule\Grid\Filter;

use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\DefaultFilterRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\Filter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\StringFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DefaultFilterRegistryUnitTest extends TestCase
{
    #[Test]
    public function addingFiltersToRegistry(): void
    {
        $filter1 = new Filter();
        $filter2 = new StringFilter();

        $registry = new DefaultFilterRegistry([$filter1, $filter2]);

        $filters = $registry->getFilters();
        $this->assertCount(2, $filters);
        $this->assertSame($filter1, $filters[0]);
        $this->assertSame($filter2, $filters[1]);
    }
}
