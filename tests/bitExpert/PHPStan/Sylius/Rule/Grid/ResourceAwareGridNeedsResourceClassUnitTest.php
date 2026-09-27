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
                    // Reported on the #[AsGrid(resourceClass: ...)] attribute at line
                    // 21, not on whichever method happened to be visited first.
                    'Resource class "App\Entity\SupplierNotFound" not found!',
                    21,
                ],
            ],
        );
    }

    /**
     * A grid is not required to declare any method. Because the rule listened on
     * MethodReturnStatementsNode before, this class was never visited at all.
     */
    #[Test]
    public function ruleChecksGridWithoutAnyMethod(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid_needs_resource_model_no_methods.php'],
            [
                [
                    'Resource class "App\Entity\SupplierNotFound" not found!',
                    17,
                ],
            ],
        );
    }

    /**
     * Since grid-bundle 1.16 a grid may implement
     * Sylius\Component\Grid\GridInterface without extending the legacy
     * AbstractGrid. The resource class of such a grid used to go unvalidated,
     * because AbstractGrid was required before the attribute was even read.
     */
    #[Test]
    public function ruleChecksGridUsingTheNewGridInterface(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\GridInterface')) {
            self::markTestSkipped('Sylius\Component\Grid\GridInterface requires grid-bundle >= 1.16.');
        }

        $this->analyse(
            [__DIR__ . '/data/grid_needs_resource_model_native_interface.php'],
            [
                [
                    'Resource class "App\Entity\SupplierNotFound" not found!',
                    35,
                ],
            ],
        );
    }
}
