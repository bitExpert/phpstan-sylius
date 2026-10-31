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
 * @extends RuleTestCase<OperationNeedsValidProcessorRule>
 */
class OperationNeedsValidProcessorUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new OperationNeedsValidProcessorRule($this->createReflectionProvider());
    }

    #[Test]
    public function ruleReportsMissingProcessorClass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_processor.php'],
            [
                [
                    'Processor class "App\Processor\ProcessorClassNotExists" not found!',
                    24,
                ],
                [
                    'Processor method "process" not found on processor class "App\Processor\CallableProcessorWrongMethod"!',
                    44,
                ],
                [
                    'Processor method "process" on processor class "App\Processor\NotStaticProcessor" has to be static when the processor is passed as callable!',
                    65,
                ],
                [
                    'Processor method "methodDoesNotExist" not found on processor class "App\Processor\CallableProcessor"!',
                    83,
                ],
            ],
        );
    }

    #[Test]
    public function ruleAcceptsValidProcessors(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_processor_valid.php'],
            [],
        );
    }

    #[Test]
    public function ruleSkipsDisabledAndNonConstantProcessors(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_processor_skip.php'],
            [],
        );
    }
}
