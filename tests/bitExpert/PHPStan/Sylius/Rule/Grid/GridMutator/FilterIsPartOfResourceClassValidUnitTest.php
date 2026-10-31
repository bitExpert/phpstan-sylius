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
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectGridNamesForGridMutatorClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\DefaultFilterRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EntityFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EnumFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\ExistsFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\Filter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\SelectFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\StringFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\GridMutator\CollectFilter;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<FilterIsPartOfResourceClass>
 */
class FilterIsPartOfResourceClassValidUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FilterIsPartOfResourceClass($this->createReflectionProvider());
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
            new CollectGridNameForGridClass(),
            new CollectGridNamesForGridMutatorClass(),
            new CollectFilter(new DefaultFilterRegistry($filters)),
        ];
    }

    #[Test]
    public function ruleSucceedsWhenAllMutatorFilterFieldsExist(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\Mutator\GridMutatorInterface')) {
            self::markTestSkipped('GridMutatorInterface requires grid-bundle >= 1.16.');
        }

        $this->analyse(
            [__DIR__ . '/../data/grid_named.php', __DIR__ . '/../data/grid_mutator_valid.php'],
            [],
        );
    }
}
