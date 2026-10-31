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
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;

/**
 * Validates the factory configured on a Create operation.
 *
 * The factory argument is validated like every other class-or-callable
 * reference on an operation (see validateClassOrCallableArgument()), so the
 * runtime's is_callable() check is honoured: factory: false disables the
 * factory, and a plain string naming an existing function is invoked
 * directly instead of being looked up as a class.
 *
 * The only factory-specific behaviour is the method resolution: when the
 * factory is given as a class name, the runtime calls the factoryMethod on
 * the looked-up service, and without a factoryMethod the ResourceBundle
 * defaults the method to "createNew" (see
 * FactoryResourceMetadataCollectionFactory).
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

        // Without a factoryMethod the ResourceBundle defaults the method to
        // createNew (FactoryResourceMetadataCollectionFactory::addDefaults()).
        // A factoryMethod that is present but not a constant string cannot be
        // resolved, so the method check is skipped entirely instead of
        // falling back to createNew: at runtime the expression wins, not the
        // default.
        $methodName = 'createNew';
        $factoryMethod = $this->extractNamedArgument($createAttribute, 'factoryMethod');
        if (null !== $factoryMethod) {
            $methodName = $factoryMethod[0] instanceof String_ ? $factoryMethod[0]->value : null;
        }

        return $this->validateClassOrCallableArgument($factoryArgument, $line, $scope, 'Factory', $methodName);
    }
}
