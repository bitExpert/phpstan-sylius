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
class ResourceAwareGridNeedsResourceClassValidUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ResourceAwareGridNeedsResourceClass($this->createReflectionProvider());
    }

    #[Test]
    public function ruleSucceedsWhenResourceClassExistsViaMethod(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid_valid.php'],
            [],
        );
    }

    #[Test]
    public function ruleSucceedsWhenResourceClassExistsViaAttribute(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid_valid_attr.php'],
            [],
        );
    }
}
