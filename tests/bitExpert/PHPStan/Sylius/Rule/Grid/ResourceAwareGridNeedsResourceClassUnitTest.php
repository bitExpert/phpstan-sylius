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
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<ResourceAwareGridNeedsResourceClass>
 */
class ResourceAwareGridNeedsResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ResourceAwareGridNeedsResourceClass($this->createReflectionProvider());
    }

    #[Test]
    public function ruleForClassmethods(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid_needs_resource_model.php'],
            [
                [
                    'Resource class "App\Entity\SupplierNotFound" not found!',
                    41,
                ],
            ],
        );
    }

    #[Test]
    public function ruleForAttr(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid_needs_resource_model_attr.php'],
            [
                [
                    'Resource class "App\Entity\SupplierNotFound" not found!',
                    24,
                ],
            ],
        );
    }
}
