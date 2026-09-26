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

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * @implements Rule<InClassNode>
 */
readonly class ResourceAwareGridNeedsResourceClass implements Rule
{
    private const AS_GRID_ATTRIBUTE = 'Sylius\Component\Grid\Attribute\AsGrid';

    private const LEGACY_GRID_CLASS = 'Sylius\Bundle\GridBundle\Grid\AbstractGrid';

    private const LEGACY_RESOURCE_AWARE_INTERFACE = 'Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface';

    public function __construct(private ReflectionProvider $broker)
    {
    }

    /**
     * @return class-string
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassNode) {
            return [];
        }

        $classReflection = $node->getClassReflection();
        $class = $node->getOriginalNode();

        // The attribute is the explicit opt-in, so it takes precedence and the
        // legacy method is not inspected a second time.
        $resourceClass = $this->resolveResourceClassFromAttribute($classReflection->getAttributes());
        if (null !== $resourceClass) {
            $error = $this->validateResourceClass($resourceClass, self::findAttributeLine($class));

            return null === $error ? [] : [$error];
        }

        $resourceClass = $this->resolveResourceClassFromMethod($classReflection, $class, $scope);
        if (null !== $resourceClass) {
            $error = $this->validateResourceClass($resourceClass, self::findMethodLine($class, 'getResourceClass'));

            return null === $error ? [] : [$error];
        }

        return [];
    }

    /**
     * The resource class declared by #[AsGrid(resourceClass: ...)]. This is
     * deliberately not gated on the grid class hierarchy: since grid-bundle 1.16
     * a grid may implement Sylius\Component\Grid\GridInterface without extending
     * Sylius\Bundle\GridBundle\Grid\AbstractGrid, and the attribute is what makes
     * the class a grid in the first place.
     *
     * @param list<\PHPStan\Reflection\AttributeReflection> $attributes
     */
    private function resolveResourceClassFromAttribute(array $attributes): ?string
    {
        foreach ($attributes as $attribute) {
            if (self::AS_GRID_ATTRIBUTE !== $attribute->getName()) {
                continue;
            }

            $argumentTypes = $attribute->getArgumentTypes();
            $resourceClass = self::resolveConstantString($argumentTypes['resourceClass'] ?? null);
            if (null !== $resourceClass) {
                return $resourceClass;
            }
        }

        return null;
    }

    /**
     * The resource class declared by the pre-1.14
     * ResourceAwareGridInterface::getResourceClass() method.
     *
     * The returned statement is read from the AST instead of the resolved method
     * return type, because a method declared 'getResourceClass(): string' that
     * returns Supplier::class has the plain type 'string' whenever PHPDoc types
     * are not treated as certain, which would lose the class name.
     */
    private function resolveResourceClassFromMethod(ClassReflection $classReflection, ClassLike $class, Scope $scope): ?string
    {
        $isSyliusGrid = $classReflection->isSubclassOf(self::LEGACY_GRID_CLASS)
            || $classReflection->implementsInterface(self::LEGACY_RESOURCE_AWARE_INTERFACE);
        if (!$isSyliusGrid) {
            return null;
        }

        $method = $class->getMethod('getResourceClass');
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

            if ($statement->expr instanceof ClassConstFetch && $statement->expr->class instanceof Name) {
                return $scope->resolveName($statement->expr->class);
            }

            return null;
        }

        return null;
    }

    private function validateResourceClass(string $resourceClass, int $line): ?IdentifierRuleError
    {
        try {
            $this->broker->getClass($resourceClass);
        } catch (ClassNotFoundException) {
            return RuleErrorBuilder::message(\sprintf('Resource class "%s" not found!', $resourceClass))
                ->identifier('sylius.grid.resourceClassRequired')
                ->line($line)
                ->build();
        }

        return null;
    }

    /**
     * Reports the error on the attribute itself rather than on the class or on
     * whichever method happened to be visited first.
     */
    private static function findAttributeLine(ClassLike $class): int
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if (self::AS_GRID_ATTRIBUTE === $attribute->name->toString()) {
                    return $attribute->getStartLine();
                }
            }
        }

        return $class->getStartLine();
    }

    /**
     * Reports the error on the getResourceClass() declaration rather than on the
     * class.
     */
    private static function findMethodLine(ClassLike $class, string $methodName): int
    {
        return $class->getMethod($methodName)?->getStartLine() ?? $class->getStartLine();
    }

    /**
     * An attribute argument is not guaranteed to be a single constant string.
     * Calling Type::getValue() on anything else raises an internal error and
     * aborts the analysis, so only single constant strings are accepted.
     */
    private static function resolveConstantString(?Type $argumentType): ?string
    {
        if (null === $argumentType) {
            return null;
        }

        $constantStrings = $argumentType->getConstantStrings();

        return 1 === \count($constantStrings) ? $constantStrings[0]->getValue() : null;
    }
}
