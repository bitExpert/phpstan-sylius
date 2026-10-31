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

use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;

/**
 * Validates the factory configured on a Create operation.
 *
 * The factory can be given as a class name (optionally with a factoryMethod),
 * as a callable ([Class::class, 'method']) or as false to disable it. When a
 * class name is given without a factoryMethod, the ResourceBundle defaults
 * the method to "createNew" (see FactoryResourceMetadataCollectionFactory), so
 * that method has to exist on the class as well.
 *
 * Only the Create operation is targeted, because factory and factoryMethod
 * are the arguments of the CreateOperation metadata only.
 */
class CreateOperationNeedsFactoryRule extends AbstractResourceOperationRule
{
    private const CREATE_ATTRIBUTE = 'Sylius\Resource\Metadata\Create';

    protected function isTargetAttribute(string $attributeName): bool
    {
        return self::CREATE_ATTRIBUTE === $attributeName;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    protected function validateAttribute(Attribute $createAttribute, Scope $scope): array
    {
        $factory = $this->extractNamedArgument($createAttribute, 'factory');
        if (null === $factory) {
            return [];
        }

        [$factoryArgument, $line] = $factory;

        if ($factoryArgument instanceof Expr\ConstFetch) {
            // factory: false disables the factory for this operation. null and
            // true are meaningless but harmless, so they are skipped as well.
            return [];
        }

        if ($factoryArgument instanceof Expr\Array_) {
            // The callable carries its own method name; a factoryMethod
            // argument is not read by the runtime for callables.
            $pair = $this->resolveCallablePair($factoryArgument, $scope);

            return null === $pair
                ? []
                : $this->validateClassReference($pair[0], $pair[1], true, $line, $scope, 'Factory');
        }

        $className = null;
        if ($factoryArgument instanceof Expr\ClassConstFetch) {
            $className = $this->resolveClassName($factoryArgument, $scope);
        } elseif ($factoryArgument instanceof String_) {
            $className = $factoryArgument->value;
        }

        if (null === $className) {
            // Anything else (variables, method calls, ...) cannot be resolved
            // statically and is skipped silently.
            return [];
        }

        $factoryMethod = $this->extractNamedArgument($createAttribute, 'factoryMethod');

        // Without a factoryMethod the ResourceBundle defaults the method to
        // createNew (FactoryResourceMetadataCollectionFactory::addDefaults()).
        // A factoryMethod that is present but not a constant string cannot be
        // resolved, so the method check is skipped entirely instead of
        // falling back to createNew: at runtime the expression wins, not the
        // default.
        $methodName = 'createNew';
        if (null !== $factoryMethod) {
            if ($factoryMethod[0] instanceof String_) {
                $methodName = $factoryMethod[0]->value;
            } else {
                $methodName = null;
            }
        }

        return $this->validateClassReference($className, $methodName, false, $line, $scope, 'Factory');
    }
}
