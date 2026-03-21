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

namespace bitExpert\PHPStan\Sylius\Rule\Resource;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<ResourceAttributeNeedsFormTypeRule>
 */
class ResourceAttributeNeedsFormTypeUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ResourceAttributeNeedsFormTypeRule($this->createReflectionProvider());
    }

    #[Test]
    public function ruleForClassmethods(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity.php'],
            [
                [
                    'Form Type "FormClassNotExists" not found!',
                    10,
                ],
            ],
        );
    }
}
