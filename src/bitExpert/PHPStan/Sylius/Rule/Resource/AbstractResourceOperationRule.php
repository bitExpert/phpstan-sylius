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

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Shared skeleton for the rules that validate a reference configured on a
 * resource operation attribute, such as the provider or the processor.
 *
 * The operation attributes are collected from the AST instead of through
 * AttributeReflection, because the operations nested in
 * AsResource(operations: [...]) live in plain new expressions inside the
 * attribute arguments and are not reachable through the reflection API.
 * Class names are resolved through the scope, honouring the namespace and
 * use statements of the analysed file.
 */
abstract class AbstractResourceOperationRule extends AbstractResourceRule
{
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassNode) {
            return [];
        }

        if (null === $this->getResourceClassReflection($scope)) {
            return [];
        }

        $errors = [];
        foreach ($this->collectTargetAttributes($node->getOriginalNode(), $scope) as $attribute) {
            $errors = [...$errors, ...$this->validateAttribute($attribute, $scope)];
        }

        return $errors;
    }

    /**
     * Returns true when the rule cares about the attribute with the given
     * fully qualified class name.
     */
    abstract protected function isTargetAttribute(string $attributeName): bool;

    /**
     * Validates a single collected attribute: one declared directly on the
     * resource class or one nested in AsResource(operations: [...]).
     *
     * @return list<IdentifierRuleError>
     */
    abstract protected function validateAttribute(Attribute $attribute, Scope $scope): array;

    /**
     * Collects the attributes of the class the rule cares about: the ones
     * declared directly on the class and the ones nested in
     * AsResource(operations: [...]). The nested ones live in a plain new
     * expression inside the attribute arguments, so they are not reachable
     * through AttributeReflection and have to be read from the AST.
     *
     * @return list<Attribute>
     */
    private function collectTargetAttributes(ClassLike $class, Scope $scope): array
    {
        $attributes = [];

        foreach ($class->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributeName = $this->resolveAttributeName($attribute->name, $scope);

                if ('Sylius\Resource\Metadata\AsResource' === $attributeName) {
                    foreach ($this->collectNestedOperationAttributes($attribute, $scope) as $nested) {
                        $attributes[] = $nested;
                    }

                    continue;
                }

                if ($this->isTargetAttribute($attributeName)) {
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }

    /**
     * Finds the operation new expressions in the operations argument of an
     * AsResource attribute.
     *
     * @return list<Attribute>
     */
    private function collectNestedOperationAttributes(Attribute $asResourceAttribute, Scope $scope): array
    {
        $operationAttributes = [];

        foreach ($asResourceAttribute->args as $arg) {
            if ('operations' !== (string) $arg->name) {
                continue;
            }

            foreach ($this->findOperationNewCalls($arg->value, $scope) as $nested) {
                $operationAttributes[] = $nested;
            }
        }

        return $operationAttributes;
    }

    /**
     * Recursively finds every new expression of a targeted operation class,
     * no matter how deeply it is nested in arrays. A New_ expression is shaped
     * like an Attribute (class name plus args), so it is treated like one
     * from here on. PHPStan's parser resolves the class names to their fully
     * qualified form, so a plain string comparison is enough.
     *
     * @return list<Attribute>
     */
    private function findOperationNewCalls(Expr $expr, Scope $scope): array
    {
        if ($expr instanceof Expr\New_ && $expr->class instanceof Name
            && $this->isTargetAttribute($this->resolveAttributeName($expr->class, $scope))) {
            // The new expression may carry raw placeholders (e.g. from a
            // first-class callable syntax) that an Attribute cannot hold, so
            // only real arguments are carried over.
            $args = [];
            foreach ($expr->args as $arg) {
                if ($arg instanceof Node\Arg) {
                    $args[] = $arg;
                }
            }

            return [new Attribute($expr->class, $args, $expr->getAttributes())];
        }

        if ($expr instanceof Expr\Array_) {
            $operationAttributes = [];
            foreach ($expr->items as $item) {
                if (null !== $item?->value) {
                    $operationAttributes = [...$operationAttributes, ...$this->findOperationNewCalls($item->value, $scope)];
                }
            }

            return $operationAttributes;
        }

        return [];
    }

    /**
     * Resolves the named argument of an operation attribute together with the
     * line it is declared on. Only named arguments are mapped: every
     * operation constructor declares many parameters before the interesting
     * ones and their positions differ between the operation classes, so
     * positional arguments cannot be resolved reliably.
     *
     * @return array{0: Expr, 1: int}|null
     */
    protected function extractNamedArgument(Attribute $attribute, string $argumentName): ?array
    {
        foreach ($attribute->args as $arg) {
            if ($argumentName === $arg->name?->toString()) {
                return [$arg->value, $arg->value->getStartLine()];
            }
        }

        return null;
    }

    /**
     * Validates an argument that the runtime accepts either as a class name
     * or as a callable: the class name form is looked up in a service
     * locator, the callable form is invoked directly. For a class name the
     * referenced class has to exist. A callable given as
     * [Class::class, 'method'] needs an existing method, and that method has
     * to be static, because the runtime invokes the callable statically and
     * is_callable() is false for instance methods.
     *
     * The optional method name is only checked for the class name form: it
     * is the method the runtime calls on the looked-up service, as the
     * factory rule passes its factoryMethod (an instance method, matching
     * $factoryInstance->$factoryMethod(...)). A callable carries its own
     * method name, so it is ignored for callables.
     *
     * @return list<IdentifierRuleError>
     */
    protected function validateClassOrCallableArgument(
        Expr $argument,
        int $line,
        Scope $scope,
        string $noun,
        ?string $methodName = null,
    ): array {
        if ($argument instanceof Expr\ConstFetch) {
            // null and false are not references, and neither is any other
            // constant expression: they are skipped as silently as the
            // runtime skips them.
            return [];
        }

        if ($argument instanceof Expr\Array_) {
            // The callable carries its own method name; the runtime invokes
            // it as a plain static callable.
            $pair = $this->resolveCallablePair($argument, $scope);

            return null === $pair
                ? []
                : $this->validateClassReference($pair[0], $pair[1], true, $line, $scope, $noun);
        }

        $className = null;
        if ($argument instanceof Expr\ClassConstFetch || $argument instanceof String_) {
            if ($argument instanceof String_ && $this->broker->hasFunction(new Name($argument->value), $scope)) {
                // A plain string naming an existing function is a valid
                // callable for the runtime: is_callable() is checked before
                // the service locator lookup, so this is not a class
                // reference.
                return [];
            }

            $className = $this->resolveClassNameFromExpression($argument, $scope);
        }

        if (null === $className) {
            // Anything else (variables, method calls, ...) cannot be resolved
            // statically and is skipped silently.
            return [];
        }

        return $this->validateClassReference($className, $methodName, false, $line, $scope, $noun);
    }

    /**
     * Checks the referenced class exists and that the method exists on it.
     * For callables the method also has to be static, because the runtime
     * invokes [Class::class, 'method'] as a plain static callable. A null
     * method name skips the method check, keeping only the class check.
     *
     * The noun ("Provider", "Processor", "Factory") shapes the error
     * messages and identifiers, so every rule emits its own
     * sylius.resource.* identifiers and the errors can be ignored or
     * baselined separately.
     *
     * @return list<IdentifierRuleError>
     */
    protected function validateClassReference(
        string $className,
        ?string $methodName,
        bool $requireStatic,
        int $line,
        Scope $scope,
        string $noun,
    ): array {
        try {
            $classReflection = $this->broker->getClass($className);
        } catch (\Throwable) {
            return [RuleErrorBuilder::message(\sprintf('%s class "%s" not found!', $noun, $className))
                ->identifier(\sprintf('sylius.resource.%sClassNotFound', \lcfirst($noun)))
                ->line($line)
                ->build()];
        }

        if (null === $methodName) {
            return [];
        }

        if (!$classReflection->hasMethod($methodName)) {
            return [RuleErrorBuilder::message(
                \sprintf(
                    '%s method "%s" not found on %s class "%s"!',
                    $noun,
                    $methodName,
                    \strtolower($noun),
                    $className,
                ),
            )
                ->identifier(\sprintf('sylius.resource.%sMethodNotFound', \lcfirst($noun)))
                ->line($line)
                ->build()];
        }

        if ($requireStatic && !$classReflection->getMethod($methodName, $scope)->isStatic()) {
            return [RuleErrorBuilder::message(
                \sprintf(
                    '%s method "%s" on %s class "%s" has to be static when the %s is passed as callable!',
                    $noun,
                    $methodName,
                    \strtolower($noun),
                    $className,
                    \strtolower($noun),
                ),
            )
                ->identifier(\sprintf('sylius.resource.%sMethodNotStatic', \lcfirst($noun)))
                ->line($line)
                ->build()];
        }

        return [];
    }

    /**
     * Resolves the class and the method name of a callable
     * [Class::class, 'method'] pair. Returns null when the pair cannot be
     * resolved statically.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function resolveCallablePair(Expr\Array_ $callable, Scope $scope): ?array
    {
        if (!$this->isCallablePair($callable)) {
            return null;
        }

        [$class, $method] = $callable->items;

        if (null === $class?->value || !$method?->value instanceof String_) {
            return null;
        }

        if ($class->value instanceof Expr\ClassConstFetch) {
            $className = $this->resolveClassName($class->value, $scope);
        } elseif ($class->value instanceof String_) {
            $className = $class->value->value;
        } else {
            return null;
        }

        if (null === $className) {
            return null;
        }

        return [$className, $method->value->value];
    }

    /**
     * A callable reference has to be a [Class::class, 'method'] pair.
     */
    private function isCallablePair(Expr\Array_ $array): bool
    {
        return 2 === \count($array->items)
            && null !== $array->items[0]
            && null !== $array->items[1];
    }

    /**
     * Resolves the class name an argument expression refers to: a
     * Class::class constant (self::, static:: and parent:: included) or a
     * plain string. Any other expression (variables, method calls, other
     * class constants) cannot be resolved statically and yields null.
     */
    protected function resolveClassNameFromExpression(Expr $argument, Scope $scope): ?string
    {
        if ($argument instanceof Expr\ClassConstFetch) {
            return $this->resolveClassName($argument, $scope);
        }

        if ($argument instanceof String_) {
            return $argument->value;
        }

        return null;
    }

    /**
     * Resolves a class name from a BookProcessor::class expression. self::,
     * static:: and parent:: are resolved through the scope as well. Any other
     * class constant (Foo::SOME_CONST) is not a class name and is skipped.
     */
    protected function resolveClassName(Expr\ClassConstFetch $classConstFetch, Scope $scope): ?string
    {
        if (!$classConstFetch->class instanceof Name) {
            return null;
        }

        if (!$classConstFetch->name instanceof Node\Identifier || 'class' !== $classConstFetch->name->toLowerString()) {
            return null;
        }

        return $scope->resolveName($classConstFetch->class);
    }

    /**
     * Resolves an attribute or new expression class name to its fully
     * qualified form, honouring the namespace and use statements of the
     * analysed file.
     */
    private function resolveAttributeName(Name $name, Scope $scope): string
    {
        return $scope->resolveName($name);
    }

    /**
     * Every attribute class extending the Operation metadata class is an
     * operation, mirroring the is_subclass_of() check of
     * AttributesResourceMetadataCollectionFactory. This keeps custom
     * operation classes and the Api operations covered as well.
     *
     * The check goes through the type system instead of
     * ClassReflection::isSubclassOfClass(), following the same pattern as
     * AbstractGridClassCollector::scopeIsGrid(): ObjectType::isSuperTypeOf()
     * is a stable public API that every PHPStan version and IDE stub set
     * knows.
     */
    protected function isOperationClassName(string $className): bool
    {
        if (!$this->broker->hasClass($className)) {
            return false;
        }

        $operationType = new ObjectType('Sylius\Resource\Metadata\Operation');

        return $operationType->isSuperTypeOf(new ObjectType($className))->yes();
    }
}
