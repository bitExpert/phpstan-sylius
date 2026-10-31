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

namespace bitExpert\PHPStan\Sylius\Rule\Grid\GridMutator;

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectGridNameForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectGridNameForGridMutatorClass;
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
use bitExpert\PHPStan\Sylius\Collector\Grid\GridMutator\CollectFilter;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FilterIsPartOfResourceClass>
 */
class FilterIsPartOfResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FilterIsPartOfResourceClass($this->createReflectionProvider());
    }

    protected function getCollectors(): array
    {
        // Mirrors the registration order in extension.neon: the catch-all has to
        // come last so it cannot shadow a node for a more specific class.
        $filters = [];
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
            new CollectGridNameForGridClass(),
            new CollectGridNameForGridMutatorClass(),
            new CollectFilter(new DefaultFilterRegistry($filters)),
        ];
    }

    public function testRule(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\Mutator\GridMutatorInterface')) {
            self::markTestSkipped('GridMutatorInterface requires grid-bundle >= 1.16.');
        }

        // The named grid has to be analyzed alongside the mutator: the mutator
        // is linked to the resource class via mutator -> grid name -> grid.
        $this->analyse(
            [__DIR__ . '/../data/grid_named.php', __DIR__ . '/../data/grid_mutator.php'],
            [
                [
                    'The filter field "missingMutatorFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    48,
                ],
                [
                    'The filter field "status123" needs to exists as property in resource class "App\Entity\Supplier".',
                    51,
                ],
                [
                    // The "address.city" entry of the fields array is skipped:
                    // filters do not support recursion via ".".
                    'The filter field "name" needs to exists as property in resource class "App\Entity\Supplier".',
                    54,
                ],
                [
                    // snake_case is converted, like every other filter field.
                    'The filter field "missingMutatorBooleanFilter" needs to exists as property in resource class "App\Entity\Supplier".',
                    57,
                ],
            ],
        );
    }

    /**
     * AsGridMutator is repeatable: one mutator attached to two grids mutates
     * both, so every filter field is validated against both resource classes.
     */
    public function testRuleValidatesRepeatableMutatorAgainstEveryGrid(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\Mutator\GridMutatorInterface')) {
            self::markTestSkipped('GridMutatorInterface requires grid-bundle >= 1.16.');
        }

        $this->analyse(
            [
                __DIR__ . '/../data/grid_named_attr.php',
                __DIR__ . '/../data/grid_named_medipack.php',
                __DIR__ . '/../data/grid_mutator_repeatable.php',
            ],
            [
                [
                    'The filter field "missingFilterOnBoth" needs to exists as property in resource class "App\Entity\Medipack".',
                    39,
                ],
                [
                    'The filter field "missingFilterOnBoth" needs to exists as property in resource class "App\Entity\Supplier".',
                    39,
                ],
            ],
        );
    }
}
