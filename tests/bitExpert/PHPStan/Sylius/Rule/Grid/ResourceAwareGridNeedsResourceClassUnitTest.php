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

/**
 * @extends RuleTestCase<ResourceAwareGridNeedsResourceClass>
 */
class ResourceAwareGridNeedsResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ResourceAwareGridNeedsResourceClass($this->createReflectionProvider());
    }

    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid.php'],
            [
                [
                    'getResourceClass() needs to provide a resource class. Mark "App\Entity\Supplier" with #[AsResource] attribute.',
                    48,
                ],
            ],
        );
    }
}
