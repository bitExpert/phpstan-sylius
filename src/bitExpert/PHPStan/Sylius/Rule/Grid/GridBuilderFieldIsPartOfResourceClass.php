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
use ReflectionMethod;

readonly class GridBuilderFieldIsPartOfResourceClass implements Rule
{
    public function __construct(private ReflectionProvider $broker)
    {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof StaticCall) {
            return [];
        }

        // run the checks only for subclasses of \Sylius\Bundle\GridBundle\Grid\AbstractGrid
        $classReflection = $scope->getClassReflection();
        $parentType = new ObjectType('\Sylius\Bundle\GridBundle\Grid\AbstractGrid');
        $classType = new ObjectType($classReflection->getName());
        if(!$parentType->isSuperTypeOf($classType)->yes()) {
            return [];
        }

        if ($node->name->toString() !== 'create') {
            return [];
        }

        // check if return type is what we expect
        $returnType = $scope->getType($node);
        $expectedReturnType = new ObjectType('\Sylius\Bundle\GridBundle\Builder\Field\FieldInterface');
        if (!$expectedReturnType->isSuperTypeOf($returnType)->yes()) {
            return [];
        }

        $methodReflection = $classReflection->getNativeMethod('getResourceClass');

        $reflectionMethod = new ReflectionMethod($classReflection->getName(), $methodReflection->getName());
        $resourceClass = $reflectionMethod->invoke(new ($classReflection->getName()));

        /** @var String_ $fieldName */
        $fieldName = $node->args[0]->value;
        $resourceClassReflection = $this->broker->getClass($resourceClass);
        if ($resourceClassReflection->hasProperty($fieldName->value)) {
            return [];
        }

        $message = sprintf(
            'The field "%s" needs to exists as property in resource class "%s".',
            $fieldName->value,
            $resourceClass
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('sylius.grid.resourceClassMissingProperty')
                ->build(),
        ];
    }

}
