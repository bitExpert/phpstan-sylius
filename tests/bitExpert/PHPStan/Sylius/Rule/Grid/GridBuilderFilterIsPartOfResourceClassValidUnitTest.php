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

namespace bitExpert\PHPStan\Sylius\Rule\Grid;

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectFilterForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\DefaultFilterRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EntityFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EnumFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\ExistsFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\Filter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\SelectFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\StringFilter;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<GridBuilderFilterIsPartOfResourceClass>
 */
class GridBuilderFilterIsPartOfResourceClassValidUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new GridBuilderFilterIsPartOfResourceClass($this->createReflectionProvider());
    }

    protected function getCollectors(): array
    {
        $filters = [];
        $filters[] = new EntityFilter();
        $filters[] = new EnumFilter();
        $filters[] = new ExistsFilter();
        $filters[] = new Filter();
        $filters[] = new SelectFilter();
        $filters[] = new StringFilter();

        return [
            new CollectRessourceClassForGridClass(),
            new CollectFilterForGridClass(new DefaultFilterRegistry($filters)),
        ];
    }

    #[Test]
    public function ruleSucceedsWhenAllFilterFieldsExist(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid_valid.php'],
            [],
        );
    }
}
