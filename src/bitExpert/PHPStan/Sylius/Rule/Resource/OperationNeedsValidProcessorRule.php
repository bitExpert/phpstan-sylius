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
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;

/**
 * Validates the processor configured on an operation.
 *
 * The processor can be given as a class name, which the runtime looks up in
 * the sylius.state_processor service locator (see Sylius\Resource\State\Processor),
 * or as a callable, which the runtime invokes directly.
 *
 * Every attribute class extending Sylius\Resource\Metadata\Operation counts
 * as an operation, which mirrors
 * AttributesResourceMetadataCollectionFactory and keeps custom operation
 * classes covered as well.
 */
class OperationNeedsValidProcessorRule extends AbstractResourceOperationRule
{
    protected function isTargetAttribute(string $attributeName): bool
    {
        return $this->isOperationClassName($attributeName);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    protected function validateAttribute(Attribute $attribute, Scope $scope): array
    {
        $processor = $this->extractNamedArgument($attribute, 'processor');
        if (null === $processor) {
            return [];
        }

        return $this->validateClassOrCallableArgument($processor[0], $processor[1], $scope, 'Processor');
    }
}
