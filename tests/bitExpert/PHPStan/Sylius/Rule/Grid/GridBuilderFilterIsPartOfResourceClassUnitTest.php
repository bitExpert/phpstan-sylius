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

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectFilterForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\BooleanFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\DateFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\DefaultFilterRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EntityFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\EnumFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\ExistsFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\Filter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\FilterNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\MoneyFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\SelectFilter;
use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\StringFilter;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<GridBuilderFilterIsPartOfResourceClass>
 */
class GridBuilderFilterIsPartOfResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new GridBuilderFilterIsPartOfResourceClass($this->createReflectionProvider());
    }

    /**
     * Overrides the registry for the test currently running. Left null the
     * production order from extension.neon is used.
     *
     * @var list<FilterNode>|null
     */
    private ?array $filterNodes = null;

    protected function getCollectors(): array
    {
        if (null !== $this->filterNodes) {
            return $this->collectorsFor($this->filterNodes);
        }

        // Mirrors the registration order in extension.neon: the catch-all has to
        // come last so it cannot shadow a node for a more specific class.
        return $this->collectorsFor([
            new EntityFilter(),
            new EnumFilter(),
            new BooleanFilter(),
            new DateFilter(),
            new MoneyFilter(),
            new ExistsFilter(),
            new SelectFilter(),
            new StringFilter(),
            new Filter(),
        ]);
    }

    /**
     * @param list<FilterNode> $filters
     *
     * @return array<Collector<Node, mixed>>
     */
    private function collectorsFor(array $filters): array
    {
        return [
            new CollectRessourceClassForGridClass(),
            new CollectFilterForGridClass(new DefaultFilterRegistry($filters)),
        ];
    }

    /**
     * A node dedicated to App\Filter\CustomFilter, i.e. what a user would
     * register for their own filter class. It reports a different field than
     * the catch-all would, so the two are distinguishable in the output.
     */
    private function dedicatedCustomFilterNode(): FilterNode
    {
        return new class implements FilterNode {
            public function supports(FullyQualified $nodeClass): bool
            {
                return 'App\\Filter\\CustomFilter' === $nodeClass->name;
            }

            public function getFilterFields(StaticCall $node): array
            {
                return ['customNodeField'];
            }
        };
    }

    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid.php'],
            [
                [
                    'The filter field "name" needs to exists as property in resource class "App\Entity\Supplier".',
                    55,
                ],
                [
                    'The filter field "name" needs to exists as property in resource class "App\Entity\Supplier".',
                    49,
                ],
                [
                    'The filter field "status123" needs to exists as property in resource class "App\Entity\Supplier".',
                    52,
                ],
                [
                    // Handled by the generic Filter node, whose supports() used to
                    // compare the subtypes the wrong way round and therefore never
                    // matched anything.
                    'The filter field "missingGenericFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    64,
                ],
                [
                    // Had no node at all, and they do not implement FilterInterface
                    // either, so the catch-all node never matched them.
                    'The filter field "missingBooleanFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    70,
                ],
                [
                    // snake_case is converted, like every other filter field.
                    'The filter field "missingDateFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    73,
                ],
                [
                    'The filter field "missingMoneyFilterField" needs to exists as property in resource class "App\Entity\Supplier".',
                    76,
                ],
            ],
        );
    }

    /**
     * Baseline: with no dedicated node registered, the catch-all resolves the
     * user-defined filter class purely because it implements FilterInterface.
     * This also proves the fixture is actually wired into grid scope.
     */
    public function testCatchAllResolvesAUserFilterByInterface(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid_custom_filter.php'],
            [
                [
                    'The filter field "catchAllOnlyField" needs to exists as property in resource class "App\Entity\Supplier".',
                    41,
                ],
                [
                    'The filter field "catchAllField" needs to exists as property in resource class "App\Entity\Supplier".',
                    45,
                ],
            ],
        );
    }

    /**
     * The documented registration order: a dedicated node comes before the
     * catch-all, so it wins for the class it targets.
     */
    public function testDedicatedNodeWinsWhenRegisteredBeforeTheCatchAll(): void
    {
        $this->filterNodes = [
            new StringFilter(),
            $this->dedicatedCustomFilterNode(),
            new Filter(),
        ];

        $this->analyse(
            [__DIR__ . '/data/grid_custom_filter.php'],
            [
                [
                    'The filter field "catchAllOnlyField" needs to exists as property in resource class "App\Entity\Supplier".',
                    41,
                ],
                [
                    // customNodeField comes from the dedicated node, not from
                    // the create() argument, so the node clearly won.
                    'The filter field "customNodeField" needs to exists as property in resource class "App\Entity\Supplier".',
                    45,
                ],
            ],
        );
    }

    /**
     * The inverse order, which is the bug the registration order guards
     * against: the catch-all is interface-based, so it matches the user's
     * filter class and the dedicated node is never consulted. The dedicated
     * field never appears in the output.
     */
    public function testCatchAllShadowsADedicatedNodeRegisteredAfterIt(): void
    {
        $this->filterNodes = [
            new StringFilter(),
            new Filter(),
            $this->dedicatedCustomFilterNode(),
        ];

        $this->analyse(
            [__DIR__ . '/data/grid_custom_filter.php'],
            [
                [
                    'The filter field "catchAllOnlyField" needs to exists as property in resource class "App\Entity\Supplier".',
                    41,
                ],
                [
                    // catchAllField is the create() argument, which is what the
                    // catch-all extracts. The dedicated node was shadowed.
                    'The filter field "catchAllField" needs to exists as property in resource class "App\Entity\Supplier".',
                    45,
                ],
            ],
        );
    }

    /**
     * The three ordering tests above drive their own registries, so on their
     * own they cannot notice extension.neon being reordered. This one reads
     * the real config and asserts the invariant that actually ships: the
     * catch-all is registered after every concrete node.
     */
    public function testCatchAllIsRegisteredLastInExtensionNeon(): void
    {
        $config = \file_get_contents(\dirname(__DIR__, 6) . '/extension.neon');
        self::assertIsString($config);

        $filterNodes = [];
        foreach (\preg_split('/^\t-$/m', $config) ?: [] as $block) {
            if (!\str_contains($block, 'phpstan.sylius.grid.filter')) {
                continue;
            }

            if (1 !== \preg_match('/^\s*class:\s*(\S+)/m', $block, $matches)) {
                self::fail('Every filter node service must declare a class.');
            }
            // NEON leaves namespace separators unescaped, so shorten by hand
            // rather than fighting backslashes in a regex.
            $filterNodes[] = \substr((string) \strrchr($matches[1], '\\'), 1);
        }

        self::assertContains('Filter', $filterNodes, 'The catch-all node must be registered.');

        $duplicates = \array_keys(\array_filter(
            \array_count_values($filterNodes),
            static fn (int $count): bool => 1 < $count,
        ));
        self::assertSame([], $duplicates, 'No filter node may be registered twice.');

        self::assertSame(
            'Filter',
            \end($filterNodes),
            'The catch-all Filter node must stay last in extension.neon, otherwise it shadows '
                . 'every node registered after it, including user-supplied ones.',
        );
    }
}
