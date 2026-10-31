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
 * @extends RuleTestCase<CreateOperationNeedsFactoryRule>
 */
class CreateOperationNeedsFactoryUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CreateOperationNeedsFactoryRule($this->createReflectionProvider());
    }

    #[Test]
    public function ruleReportsMissingFactoryClass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_factory.php'],
            [
                [
                    'Factory class "App\Factory\FactoryClassNotExists" not found!',
                    26,
                ],
                [
                    'Factory method "methodDoesNotExist" not found on factory class "App\Factory\MethodNotExistsFactory"!',
                    47,
                ],
                [
                    'Factory method "createNew" not found on factory class "App\Factory\ClassOnlyFactory"!',
                    68,
                ],
                [
                    'Factory class "App\Factory\CallableFactoryClassNotExists" not found!',
                    87,
                ],
                [
                    'Factory method "create" on factory class "App\Factory\CallableFactoryNotStatic" has to be static when the factory is passed as callable!',
                    108,
                ],
                [
                    'Factory method "create" not found on factory class "App\Factory\CallableFactoryWrongMethod"!',
                    127,
                ],
            ],
        );
    }

    #[Test]
    public function ruleAcceptsValidFactories(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_factory_valid.php'],
            [],
        );
    }

    #[Test]
    public function ruleSkipsDisabledAndNonConstantFactories(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_factory_skip.php'],
            [
                [
                    'Factory method "methodDoesNotExist" not found on factory class "App\Factory\CallableFactory"!',
                    79,
                ],
            ],
        );
    }
}
