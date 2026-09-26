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
 * @extends RuleTestCase<IndexOperationNeedsGridClassRule>
 */
class IndexOperationNeedsGridClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new IndexOperationNeedsGridClassRule($this->createReflectionProvider());
    }

    #[Test]
    public function ruleReportsMissingGridClass(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_index.php'],
            [
                [
                    'Grid class "App\Grid\GridClassNotExists" not found!',
                    10,
                ],
            ],
        );
    }

    /**
     * The fixture mixes AsResource and Index attributes, including constant-array
     * and object arguments. None of those are a single constant string, so this
     * rule must skip all of them. Before the fix, Index(grid: [self::class])
     * raised an internal error and aborted the analysis.
     */
    #[Test]
    public function ruleSkipsNonConstantGridArgument(): void
    {
        $this->analyse(
            [__DIR__ . '/data/entity_non_constant_attribute.php'],
            [],
        );
    }
}
