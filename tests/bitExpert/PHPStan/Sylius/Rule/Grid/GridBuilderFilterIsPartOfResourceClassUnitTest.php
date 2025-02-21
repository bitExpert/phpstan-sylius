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

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

class GridBuilderFilterIsPartOfResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new GridBuilderFilterIsPartOfResourceClass($this->createReflectionProvider());
    }

    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid.php'],
            [
                [
                    'The filter field "name" needs to exists as property in resource class "App\Entity\Supplier".',
                    44,
                ],
            ],
        );
    }
}
