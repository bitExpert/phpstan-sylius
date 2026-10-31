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
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Validates the provider configured on an operation.
 *
 * The provider can be given as a class name, which the runtime looks up in
 * the sylius.state_provider service locator (see Sylius\Resource\State\Provider),
 * or as a callable, which the runtime invokes directly. For a class name the
 * referenced class has to exist. A callable given as [Class::class, 'method']
 * needs an existing method, and that method has to be static, because the
 * runtime invokes the callable statically and is_callable() is false for
 * instance methods.
 *
 * The provider argument is read from every operation attribute of a resource
 * class: the ones declared directly on the class and the ones nested as
 * new expressions in AsResource(operations: [...]). Every attribute class
 * extending Sylius\Resource\Metadata\Operation counts as an operation, which
 * mirrors AttributesResourceMetadataCollectionFactory and keeps custom
 * operation classes covered as well. The nested operations live in plain new
 * expressions inside the attribute arguments, so they are not reachable
 * through AttributeReflection and have to be read from the AST.
 *
 * @implements Rule<InClassNode>
 */
class OperationNeedsValidProviderRule implements Rule
{
    public function __construct(private ReflectionProvider $broker)
    {
    }

    /**
     * @return class-string<InClassNode>
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

        $classReflection = $scope->getClassReflection();
        if ((null === $classReflection) || (!$classReflection->implementsInterface('Sylius\Resource\Model\ResourceInterface'))) {
            return [];
        }

        $class = $node->getOriginalNode();

        $errors = [];
        foreach ($this->collectProviderExpressions($class, $scope) as [$provider, $line]) {
            $errors = [...$errors, ...$this->validateProvider($provider, $line, $scope)];
        }

        return $errors;
    }

    /**
     * Collects the provider expression of every operation attribute of the
     * class: the ones declared directly on the class and the ones nested in
     * AsResource(operations: [...]). Nested operations live in a plain new
     * expression inside the attribute arguments, so they are not reachable
     * through AttributeReflection and have to be read from the AST.
     *
     * @return list<array{0: Expr, 1: int}>
     */
    private function collectProviderExpressions(ClassLike $class, Scope $scope): array
    {
        $providers = [];

        foreach ($class->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributeName = $this->resolveAttributeName($attribute->name, $scope);

                if ('Sylius\Resource\Metadata\AsResource' === $attributeName) {
                    foreach ($this->collectNestedOperationAttributes($attribute, $scope) as $nested) {
                        $providers = [...$providers, ...$this->extractProviderArgument($nested)];
                    }

                    continue;
                }

                if ($this->isOperationClassName($attributeName)) {
                    $providers = [...$providers, ...$this->extractProviderArgument($attribute)];
                }
            }
        }

        return $providers;
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
     * Recursively finds every new expression of an operation class, no matter
     * how deeply it is nested in arrays. A New_ expression is shaped like an
     * Attribute (class name plus args), so it is treated like one from here
     * on. PHPStan's parser resolves the class names to their fully qualified
     * form, so a plain string comparison is enough.
     *
     * @return list<Attribute>
     */
    private function findOperationNewCalls(Expr $expr, Scope $scope): array
    {
        if ($expr instanceof Expr\New_ && $expr->class instanceof Name
            && $this->isOperationClassName($this->resolveAttributeName($expr->class, $scope))) {
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
     * Resolves the named provider argument of an operation attribute. Only
     * named arguments are mapped: every operation constructor declares many
     * parameters before provider and their position differs between the
     * operation classes, so positional arguments cannot be resolved reliably.
     *
     * @return list<array{0: Expr, 1: int}>
     */
    private function extractProviderArgument(Attribute $operationAttribute): array
    {
        foreach ($operationAttribute->args as $arg) {
            if ('provider' === $arg->name?->toString()) {
                return [[$arg->value, $arg->value->getStartLine()]];
            }
        }

        return [];
    }

    /**
     * Validates a single provider expression.
     *
     * @return list<IdentifierRuleError>
     */
    private function validateProvider(Expr $provider, int $line, Scope $scope): array
    {
        if ($provider instanceof Expr\ConstFetch) {
            // null and false are not provider references, and neither is any
            // other constant expression: they are skipped as silently as the
            // runtime skips them.
            return [];
        }

        if ($provider instanceof Expr\Array_ && $this->isCallablePair($provider)) {
            // The callable carries its own method name; the runtime invokes
            // it as a plain static callable.
            [$class, $method] = $provider->items;

            if (null === $class?->value || !$method?->value instanceof String_) {
                return [];
            }

            if ($class->value instanceof Expr\ClassConstFetch) {
                $className = $this->resolveClassName($class->value, $scope);
            } elseif ($class->value instanceof String_) {
                $className = $class->value->value;
            } else {
                return [];
            }

            if (null === $className) {
                return [];
            }

            return $this->validateProviderClass(
                $className,
                $method->value->value,
                true,
                $line,
                $scope,
            );
        }

        $className = null;
        if ($provider instanceof Expr\ClassConstFetch) {
            $className = $this->resolveClassName($provider, $scope);
        } elseif ($provider instanceof String_) {
            if ($this->broker->hasFunction(new Name($provider->value), $scope)) {
                // A plain string naming an existing function is a valid
                // callable for the runtime: is_callable() is checked before
                // the service locator lookup, so this is not a class
                // reference.
                return [];
            }

            $className = $provider->value;
        }

        if (null === $className) {
            // Anything else (variables, method calls, ...) cannot be resolved
            // statically and is skipped silently.
            return [];
        }

        return $this->validateProviderClass($className, null, false, $line, $scope);
    }

    /**
     * Checks the provider class exists and that the provider method exists on
     * it. For callables the method also has to be static, because the runtime
     * invokes [Class::class, 'method'] as a plain static callable. A null
     * method name skips the method check, keeping only the class check.
     *
     * @return list<IdentifierRuleError>
     */
    private function validateProviderClass(string $className, ?string $methodName, bool $requireStatic, int $line, Scope $scope): array
    {
        try {
            $classReflection = $this->broker->getClass($className);
        } catch (\Throwable) {
            return [RuleErrorBuilder::message(\sprintf('Provider class "%s" not found!', $className))
                ->identifier('sylius.resource.providerClassNotFound')
                ->line($line)
                ->build()];
        }

        if (null === $methodName) {
            return [];
        }

        if (!$classReflection->hasMethod($methodName)) {
            return [RuleErrorBuilder::message(
                \sprintf('Provider method "%s" not found on provider class "%s"!', $methodName, $className),
            )
                ->identifier('sylius.resource.providerMethodNotFound')
                ->line($line)
                ->build()];
        }

        if ($requireStatic && !$classReflection->getMethod($methodName, $scope)->isStatic()) {
            return [RuleErrorBuilder::message(
                \sprintf(
                    'Provider method "%s" on provider class "%s" has to be static when the provider is passed as callable!',
                    $methodName,
                    $className,
                ),
            )
                ->identifier('sylius.resource.providerMethodNotStatic')
                ->line($line)
                ->build()];
        }

        return [];
    }

    /**
     * A callable provider has to be a [Class::class, 'method'] pair.
     */
    private function isCallablePair(Expr\Array_ $array): bool
    {
        return 2 === \count($array->items)
            && null !== $array->items[0]
            && null !== $array->items[1];
    }

    /**
     * Resolves a class name from a BookProvider::class expression. self::,
     * static:: and parent:: are resolved through the scope as well. Any other
     * class constant (Foo::SOME_CONST) is not a class name and is skipped.
     */
    private function resolveClassName(Expr\ClassConstFetch $classConstFetch, Scope $scope): ?string
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
    private function isOperationClassName(string $className): bool
    {
        if (!$this->broker->hasClass($className)) {
            return false;
        }

        $operationType = new ObjectType('Sylius\Resource\Metadata\Operation');

        return $operationType->isSuperTypeOf(new ObjectType($className))->yes();
    }
}
