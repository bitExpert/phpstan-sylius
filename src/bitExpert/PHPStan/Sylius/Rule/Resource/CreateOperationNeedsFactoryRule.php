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
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates the factory configured on a Create operation.
 *
 * The factory can be given as a class name (optionally with a factoryMethod),
 * as a callable ([Class::class, 'method']) or as false to disable it. When a
 * class name is given without a factoryMethod, the ResourceBundle defaults
 * the method to "createNew" (see FactoryResourceMetadataCollectionFactory), so
 * that method has to exist on the class as well.
 *
 * @implements Rule<InClassNode>
 */
class CreateOperationNeedsFactoryRule implements Rule
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
        foreach ($this->collectCreateAttributes($class, $scope) as $createAttribute) {
            $errors = [...$errors, ...$this->processCreateAttribute($createAttribute, $scope)];
        }

        return $errors;
    }

    /**
     * Collects every Create attribute of the class: the ones declared directly
     * on the class and the ones nested in AsResource(operations: [...]). The
     * nested ones live in a plain new expression inside the attribute
     * arguments, so they are not reachable through AttributeReflection and
     * have to be read from the AST.
     *
     * @return list<Attribute>
     */
    private function collectCreateAttributes(ClassLike $class, Scope $scope): array
    {
        $createAttributes = [];

        foreach ($class->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                if ('Sylius\Resource\Metadata\Create' === $attribute->name->toString()) {
                    $createAttributes[] = $attribute;
                }

                if ('Sylius\Resource\Metadata\AsResource' === $attribute->name->toString()) {
                    foreach ($this->collectNestedCreateAttributes($attribute, $scope) as $nested) {
                        $createAttributes[] = $nested;
                    }
                }
            }
        }

        return $createAttributes;
    }

    /**
     * Finds the Create new expressions in the operations argument of an
     * AsResource attribute.
     *
     * @return list<Attribute>
     */
    private function collectNestedCreateAttributes(Attribute $asResourceAttribute, Scope $scope): array
    {
        $createAttributes = [];

        foreach ($asResourceAttribute->args as $arg) {
            if ('operations' !== (string) $arg->name) {
                continue;
            }

            foreach ($this->findCreateNewCalls($arg->value, $scope) as $nested) {
                $createAttributes[] = $nested;
            }
        }

        return $createAttributes;
    }

    /**
     * Recursively finds every new Create(...) expression, no matter how
     * deeply it is nested in arrays. A New_ expression is shaped like an
     * Attribute (class name plus args), so it is treated like one from here
     * on. PHPStan's parser resolves the class names to their fully qualified
     * form, so a plain string comparison is enough.
     *
     * @return list<Attribute>
     */
    private function findCreateNewCalls(Expr $expr, Scope $scope): array
    {
        if ($expr instanceof Expr\New_ && $expr->class instanceof Node\Name
            && 'Sylius\Resource\Metadata\Create' === $expr->class->toString()) {
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
            $createAttributes = [];
            foreach ($expr->items as $item) {
                if (null !== $item?->value) {
                    $createAttributes = [...$createAttributes, ...$this->findCreateNewCalls($item->value, $scope)];
                }
            }

            return $createAttributes;
        }

        return [];
    }

    /**
     * Validates a single Create attribute (declared or nested).
     *
     * @return list<IdentifierRuleError>
     */
    private function processCreateAttribute(Attribute $createAttribute, Scope $scope): array
    {
        [$factory, $factoryMethod] = $this->resolveArguments($createAttribute);

        if (null === $factory) {
            return [];
        }

        if ($factory instanceof Expr\ConstFetch) {
            // factory: false disables the factory for this operation. null and
            // true are meaningless but harmless, so they are skipped as well.
            return [];
        }

        if ($factory instanceof Expr\Array_ && $this->isCallablePair($factory)) {
            // The callable carries its own method name; a factoryMethod
            // argument is not read by the runtime for callables.
            [$class, $method] = $factory->items;

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

            return $this->validateFactoryClass(
                $className,
                $method->value->value,
                true,
                $factory->getStartLine(),
                $scope,
            );
        }

        $className = null;
        if ($factory instanceof Expr\ClassConstFetch) {
            $className = $this->resolveClassName($factory, $scope);
        } elseif ($factory instanceof String_) {
            $className = $factory->value;
        }

        if (null === $className) {
            // Anything else (variables, method calls, ...) cannot be resolved
            // statically and is skipped silently.
            return [];
        }

        // Without a factoryMethod the ResourceBundle defaults the method to
        // createNew (FactoryResourceMetadataCollectionFactory::addDefaults()).
        // A factoryMethod that is present but not a constant string cannot be
        // resolved, so the method check is skipped entirely instead of
        // falling back to createNew: at runtime the expression wins, not the
        // default.
        $methodName = 'createNew';
        $checkMethod = true;
        if (null !== $factoryMethod) {
            if ($factoryMethod instanceof String_) {
                $methodName = $factoryMethod->value;
            } else {
                $checkMethod = false;
            }
        }

        return $this->validateFactoryClass(
            $className,
            $checkMethod ? $methodName : null,
            false,
            $factory->getStartLine(),
            $scope,
        );
    }

    /**
     * Resolves the factory and factoryMethod arguments of the Create
     * attribute. Only named arguments are mapped: the Create constructor
     * declares more than twenty parameters before factory and factoryMethod,
     * so positional arguments cannot be resolved reliably.
     *
     * @return array{0: Expr|null, 1: Expr|null}
     */
    private function resolveArguments(Attribute $createAttribute): array
    {
        $factory = null;
        $factoryMethod = null;

        foreach ($createAttribute->args as $arg) {
            $argumentName = $arg->name?->toString();

            if ('factory' === $argumentName) {
                $factory = $arg->value;
            } elseif ('factoryMethod' === $argumentName) {
                $factoryMethod = $arg->value;
            }
        }

        return [$factory, $factoryMethod];
    }

    /**
     * A callable factory has to be a [Class::class, 'method'] pair.
     */
    private function isCallablePair(Expr\Array_ $array): bool
    {
        return 2 === \count($array->items)
            && null !== $array->items[0]
            && null !== $array->items[1];
    }

    /**
     * Resolves a class name from a BookFactory::class expression. self::,
     * static:: and parent:: are resolved through the scope as well. Any other
     * class constant (Foo::SOME_CONST) is not a class name and is skipped.
     */
    private function resolveClassName(Expr\ClassConstFetch $classConstFetch, Scope $scope): ?string
    {
        if (!$classConstFetch->class instanceof Node\Name) {
            return null;
        }

        if (!$classConstFetch->name instanceof Node\Identifier || 'class' !== $classConstFetch->name->toLowerString()) {
            return null;
        }

        return $scope->resolveName($classConstFetch->class);
    }

    /**
     * Checks the factory class exists and that the factory method exists on
     * it. For callables the method also has to be static, because the runtime
     * invokes [Class::class, 'method'] as a plain static callable. A null
     * method name skips the method check, keeping only the class check.
     *
     * @return list<IdentifierRuleError>
     */
    private function validateFactoryClass(string $className, ?string $methodName, bool $requireStatic, int $line, Scope $scope): array
    {
        try {
            $classReflection = $this->broker->getClass($className);
        } catch (\Throwable) {
            return [RuleErrorBuilder::message(\sprintf('Factory class "%s" not found!', $className))
                ->identifier('sylius.resource.factoryClassNotFound')
                ->line($line)
                ->build()];
        }

        if (null === $methodName) {
            return [];
        }

        if (!$classReflection->hasMethod($methodName)) {
            return [RuleErrorBuilder::message(
                \sprintf('Factory method "%s" not found on factory class "%s"!', $methodName, $className),
            )
                ->identifier('sylius.resource.factoryMethodNotFound')
                ->line($line)
                ->build()];
        }

        if ($requireStatic && !$classReflection->getMethod($methodName, $scope)->isStatic()) {
            return [RuleErrorBuilder::message(
                \sprintf(
                    'Factory method "%s" on factory class "%s" has to be static when the factory is passed as callable!',
                    $methodName,
                    $className,
                ),
            )
                ->identifier('sylius.resource.factoryMethodNotStatic')
                ->line($line)
                ->build()];
        }

        return [];
    }
}
