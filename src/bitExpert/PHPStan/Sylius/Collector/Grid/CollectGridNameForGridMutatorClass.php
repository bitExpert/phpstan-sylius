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

namespace bitExpert\PHPStan\Sylius\Collector\Grid;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Collects the grid names a grid mutator is attached to via its
 * #[AsGridMutator(grid: '...')] attributes. The names are the join key to the
 * grid classes (and through them to the resource classes) the mutator
 * mutates.
 *
 * AsGridMutator is repeatable: one mutator class can mutate several grids, in
 * which case every grid name is collected, mirroring the GridMutatorPass which
 * registers the mutator once per attribute.
 *
 * @implements Collector<InClassNode, array{string, non-empty-list<string>, int}>
 */
final class CollectGridNameForGridMutatorClass extends AbstractGridAttributeCollector implements Collector
{
    private const ATTRIBUTE = 'Sylius\Component\Grid\Attribute\AsGridMutator';

    /**
     * @return class-string<InClassNode>
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return array{string, non-empty-list<string>, int}|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof InClassNode) {
            return null;
        }

        $classReflection = $node->getClassReflection();

        $gridNames = [];
        $line = $node->getLine();
        foreach ($this->getAttributeArgumentTypes($classReflection, self::ATTRIBUTE) as $argumentTypes) {
            $gridArgument = $argumentTypes['grid'] ?? ($argumentTypes[0] ?? null);
            if (null === $gridArgument) {
                continue;
            }

            $gridName = self::resolveConstantString($gridArgument);
            if (null === $gridName) {
                continue;
            }

            $gridNames[] = $gridName;
            $line = $node->getOriginalNode()->getStartLine();
        }

        if (0 === \count($gridNames)) {
            return null;
        }

        return [$classReflection->getName(), $gridNames, $line];
    }
}
