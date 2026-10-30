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

use bitExpert\PHPStan\Sylius\Collector\Grid\Filter\FilterRegistry;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\Type;

/**
 * Collects the filter factory calls of a grid builder, no matter whether the
 * builder is used inside a grid class or inside a grid mutator.
 *
 * @implements Collector<StaticCall, array{string, non-empty-array<string>, -1|int<1, max>}>
 */
abstract class AbstractGridBuilderFilterCollector extends AbstractGridBuilderCallCollector implements Collector
{
    private const FACTORY_METHODS = ['create'];

    public function __construct(private readonly FilterRegistry $filterRegistry)
    {
    }

    /**
     * Whether the current scope belongs to a grid or to a grid mutator.
     */
    abstract protected function scopeIsBuilderScope(Scope $scope): bool;

    protected function isBuilderCall(StaticCall $node, Scope $scope): bool
    {
        if ((!$node->name instanceof Identifier) || !\in_array($node->name->toString(), self::FACTORY_METHODS, true)) {
            return false;
        }

        if (!$this->scopeIsBuilderScope($scope)) {
            return false;
        }

        return $this->isFilterInterfaceReturnType($scope->getType($node));
    }

    /**
     * @return array{string, non-empty-array<string>, -1|int<1, max>}|null
     */
    protected function collect(StaticCall $node, string $className): ?array
    {
        // first check if the various filter implementations have defined custom fields to filter on
        $filterFields = [];
        /** @var FullyQualified $nodeClass */
        $nodeClass = $node->class;

        foreach ($this->filterRegistry->getFilters() as $filter) {
            if ($filter->supports($nodeClass)) {
                $filterFields = $filter->getFilterFields($node);
                break;
            }
        }

        if (0 === \count($filterFields)) {
            return null;
        }

        return [$className, $filterFields, $node->getLine()];
    }

    protected function isFilterInterfaceReturnType(Type $type): bool
    {
        // new Grid Bundle logic since v1.16.0
        if ($this->isSubtypeOf($type, '\Sylius\Component\Grid\Builder\Filter\FilterInterface')) {
            return true;
        }

        // old Grid Bundle logic
        return $this->isSubtypeOf($type, '\Sylius\Bundle\GridBundle\Builder\Filter\FilterInterface');
    }
}
