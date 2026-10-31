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
 * @extends RuleTestCase<OperationNeedsValidProviderRule>
 */
class OperationNeedsValidProviderUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new OperationNeedsValidProviderRule($this->createReflectionProvider());
    }

    #[Test]
    public function ruleReportsMissingProviderClass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_provider.php'],
            [
                [
                    'Provider class "App\Provider\ProviderClassNotExists" not found!',
                    24,
                ],
                [
                    'Provider method "provide" not found on provider class "App\Provider\CallableProviderWrongMethod"!',
                    44,
                ],
                [
                    'Provider method "provide" on provider class "App\Provider\NotStaticProvider" has to be static when the provider is passed as callable!',
                    65,
                ],
                [
                    'Provider method "methodDoesNotExist" not found on provider class "App\Provider\CallableProvider"!',
                    83,
                ],
            ],
        );
    }

    #[Test]
    public function ruleAcceptsValidProviders(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_provider_valid.php'],
            [],
        );
    }

    #[Test]
    public function ruleSkipsDisabledAndNonConstantProviders(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_provider_skip.php'],
            [],
        );
    }
}
