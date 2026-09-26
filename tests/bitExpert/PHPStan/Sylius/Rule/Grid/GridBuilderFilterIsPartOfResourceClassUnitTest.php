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
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\BooleanFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\DateFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\DefaultFilterRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EntityFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EnumFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\ExistsFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\Filter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\MoneyFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\SelectFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\StringFilter;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<GridBuilderFilterIsPartOfResourceClass>
 */
class GridBuilderFilterIsPartOfResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new GridBuilderFilterIsPartOfResourceClass($this->createReflectionProvider());
    }

    protected function getCollectors(): array
    {
        $filters = [];
        // Mirrors the registration order in extension.neon: the catch-all has to
        // come last so it cannot shadow a node for a more specific class.
        $filters[] = new EntityFilter();
        $filters[] = new EnumFilter();
        $filters[] = new BooleanFilter();
        $filters[] = new DateFilter();
        $filters[] = new MoneyFilter();
        $filters[] = new ExistsFilter();
        $filters[] = new SelectFilter();
        $filters[] = new StringFilter();
        $filters[] = new Filter();

        return [
            new CollectRessourceClassForGridClass(),
            new CollectFilterForGridClass(new DefaultFilterRegistry($filters)),
        ];
    }

    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid.php'],
            [
                [
                    'The filter field "name" needs to exists as property in resource class "App\Entity\Supplier".',
                    55,
                ],
                [
                    'The filter field "name" needs to exists as property in resource class "App\Entity\Supplier".',
                    49,
                ],
                [
                    'The filter field "status123" needs to exists as property in resource class "App\Entity\Supplier".',
                    52,
                ],
                [
                    // Handled by the generic Filter node, whose supports() used to
                    // compare the subtypes the wrong way round and therefore never
                    // matched anything.
                    'The filter field "missingGenericFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    64,
                ],
                [
                    // Had no node at all, and they do not implement FilterInterface
                    // either, so the catch-all node never matched them.
                    'The filter field "missingBooleanFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    70,
                ],
                [
                    // snake_case is converted, like every other filter field.
                    'The filter field "missingDateFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    73,
                ],
                [
                    'The filter field "missingMoneyFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    76,
                ],
            ],
        );
    }
}
