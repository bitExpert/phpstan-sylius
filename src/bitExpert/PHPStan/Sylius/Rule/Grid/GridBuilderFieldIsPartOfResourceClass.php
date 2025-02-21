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
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use ReflectionMethod;

readonly class GridBuilderFieldIsPartOfResourceClass extends AbstractGridBuilderRule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof StaticCall) {
            return [];
        }

        if ($node->name->toString() !== 'create') {
            return [];
        }

        if(!$this->scopeIsAbstractGridSubclass($scope)) {
            return [];
        }

        if(!$this->isFieldInterfaceReturnType($scope->getType($node))) {
            return [];
        }

        /** @var String_ $fieldName */
        $fieldName = $node->args[0]->value;
        $resourceClassReflection = $this->getResourceClassEntity($scope);
        if ($resourceClassReflection->hasProperty($fieldName->value)) {
            return [];
        }

        $message = sprintf(
            'The field "%s" needs to exists as property in resource class "%s".',
            $fieldName->value,
            $resourceClassReflection->getName()
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('sylius.grid.resourceClassMissingProperty')
                ->build(),
        ];
    }

    private function isFieldInterfaceReturnType(Type $type): bool
    {
        try {
            $expectedReturnType = new ObjectType('\Sylius\Bundle\GridBundle\Builder\Field\FieldInterface');
            return $expectedReturnType->isSuperTypeOf($type)->yes();
        } catch (\Throwable $e) {
        }

        return false;
    }
}
