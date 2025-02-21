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

namespace bitExpert\PHPStan\Sylius\Rule;

use PHPStan\Testing\RuleTestCase;
use PHPStan\Reflection\ReflectionProvider;

class GridBuilderFieldIsPartOfResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): \PHPStan\Rules\Rule
    {
        return new GridBuilderFieldIsPartOfResourceClass($this->createReflectionProvider());
    }

    public function testRule(): void
    {
        $this->analyse(
            [ __DIR__ . '/data/grid.php'],
            [
                [
                    'The field "name" needs to exists as property in resource class "App\Entity\Supplier".',
                    40,
                ],
            ]
        );
    }
}
