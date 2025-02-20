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

class ResourceAwareGridNeedsResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): \PHPStan\Rules\Rule
    {
        // getRule() method needs to return an instance of the tested rule
        return new ResourceAwareGridNeedsResourceClass();
    }

    public function testRule(): void
    {
        $this->analyse(
            [ __DIR__ . '/data/grid.php'],
            [
                [
                    'getResourceClass() needs to provide a resource class. Mark "App\Entity\Supplier" with #[AsResource] attribute.',
                    37,
                ],
            ]
        );
    }
}
