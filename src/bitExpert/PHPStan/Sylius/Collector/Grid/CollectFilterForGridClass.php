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
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * @implements Collector<StaticCall, array{string, non-empty-array<string>, -1|int<1, max>}>
 */
final class CollectFilterForGridClass extends AbstractGridClassCollector implements Collector
{
    public function __construct(private readonly FilterRegistry $filterRegistry)
    {
    }

    /**
     * @return class-string
     */
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof StaticCall) {
            return null;
        }

        if ((!$node->name instanceof Identifier) || ('create' !== $node->name->toString())) {
            return null;
        }

        if (!$this->scopeIsAbstractGridSubclass($scope)) {
            return null;
        }

        if (!$this->isFilterInterfaceReturnType($scope->getType($node))) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return null;
        }
        $classType = new ObjectType($classReflection->getName());

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

        return [$classType->getClassName(), $filterFields, $node->getLine()];
    }

    protected function isFilterInterfaceReturnType(Type $type): bool
    {
        return $this->isSubtypeOf($type, '\Sylius\Bundle\GridBundle\Builder\Filter\FilterInterface');
    }
}
