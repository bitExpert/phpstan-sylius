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
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * Collects the name of every grid class, plus the resource class when it is
 * declared via the AsGrid attribute. Grid mutators reference grids by name
 * (#[AsGridMutator(grid: '...')]), so the name is the join key between a
 * mutator and the resource class of the grid it mutates.
 *
 * The name is derived the way grid-bundle derives it:
 * 1. #[AsGrid(name: '...')] on the class,
 * 2. the value returned by getName(), when it is a plain constant string,
 * 3. the class name, which is the default the InvokableGridPass applies
 *    ('name' => $attribute->name ?? $class) and the convention mutators rely on.
 *
 * The resource class is only collected from the AsGrid attribute. A grid
 * that declares its resource class via the legacy getResourceClass() method
 * is joined through CollectRessourceClassForGridClass instead, which is why
 * this collector runs on InClassNode: a 1.16 grid may declare nothing but
 * the attribute, without a single method.
 *
 * @implements Collector<InClassNode, array{string, string, string|null, int}>
 */
final class CollectGridNameForGridClass extends AbstractGridClassCollector implements Collector
{
    private const AS_GRID_ATTRIBUTE = 'Sylius\Component\Grid\Attribute\AsGrid';

    /**
     * @return class-string<InClassNode>
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return array{string, string, string|null, int}|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof InClassNode) {
            return null;
        }

        $classReflection = $node->getClassReflection();
        $classLike = $node->getOriginalNode();

        [$attributeName, $resourceClassName] = $this->resolveAttributeValues($classReflection->getAttributes());

        // The attribute is the explicit opt-in; everything else is only
        // collected for an actual grid class, so a getName() method on some
        // unrelated class is never mistaken for a grid name.
        $gridName = $attributeName;
        if (null === $gridName && $this->scopeIsGrid($scope)) {
            $gridName = $this->resolveNameFromGetNameMethod($classLike, $scope);
            $gridName ??= $classReflection->getName();
        }

        if (null === $gridName) {
            return null;
        }

        return [$classReflection->getName(), $gridName, $resourceClassName, $classLike->getStartLine()];
    }

    /**
     * The name and the resource class declared by #[AsGrid(...)]: 'name' when
     * it is a constant string, 'resourceClass' likewise. Deliberately not
     * gated on the grid class hierarchy: since grid-bundle 1.16 a grid may
     * implement Sylius\Component\Grid\GridInterface without extending the
     * legacy AbstractGrid, and then the attribute is what makes the class a
     * grid.
     *
     * @param list<\PHPStan\Reflection\AttributeReflection> $attributes
     *
     * @return array{string|null, string|null}
     */
    private function resolveAttributeValues(array $attributes): array
    {
        $gridName = null;
        $resourceClassName = null;

        foreach ($attributes as $attribute) {
            if (self::AS_GRID_ATTRIBUTE !== $attribute->getName()) {
                continue;
            }

            $argumentTypes = $attribute->getArgumentTypes();

            $gridName = self::resolveConstantString($argumentTypes['name'] ?? null) ?? $gridName;
            $resourceClassName = self::resolveConstantString($argumentTypes['resourceClass'] ?? null) ?? $resourceClassName;
        }

        return [$gridName, $resourceClassName];
    }

    /**
     * The value a legacy grid returns from getName(), when it is a plain
     * constant string or a class constant. Returns null when the class has no
     * getName() method or its return value cannot be resolved statically.
     */
    private function resolveNameFromGetNameMethod(ClassLike $classLike, Scope $scope): ?string
    {
        $method = $classLike->getMethod('getName');
        if (null === $method) {
            return null;
        }

        foreach ($method->stmts ?? [] as $statement) {
            if (!$statement instanceof Return_ || null === $statement->expr) {
                continue;
            }

            if ($statement->expr instanceof String_) {
                return $statement->expr->value;
            }

            if ($statement->expr instanceof ClassConstFetch && $statement->expr->class instanceof Node\Name) {
                return $scope->resolveName($statement->expr->class);
            }

            return null;
        }

        return null;
    }

    /**
     * An attribute argument is not guaranteed to be a single constant string.
     * Anything else cannot be joined on, so only constant strings are accepted.
     */
    private static function resolveConstantString(?\PHPStan\Type\Type $argumentType): ?string
    {
        if (null === $argumentType) {
            return null;
        }

        $constantStrings = $argumentType->getConstantStrings();

        return 1 === \count($constantStrings) ? $constantStrings[0]->getValue() : null;
    }
}
