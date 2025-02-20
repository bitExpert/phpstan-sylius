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

namespace bitExpert\PHPStan\Sylius\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\String_;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;

class ResourceAwareGridNeedsResourceClass implements Rule
{
    public function __construct(private readonly ReflectionProvider $broker)
    {
    }

    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodReturnStatementsNode) {
            return [];
        }

        // run the checks only for subclasses of \Sylius\Bundle\GridBundle\Grid\AbstractGrid
        $classReflection = $scope->getClassReflection();
        $parentType = new ObjectType('\Sylius\Bundle\GridBundle\Grid\AbstractGrid');
        $classType = new ObjectType($classReflection->getName());
        if(!$parentType->isSuperTypeOf($classType)->yes()) {
            return [];
        }

        // we are only interested in the getResourceClass() method
        $methodReflection = $node->getMethodReflection();
        if ($methodReflection->getName() !== 'getResourceClass') {
            return [];
        }

        $resourceClassName = '';
        $statements = $node->getStatements();
        if ($statements[0]->expr instanceof String_) {
            $resourceClassName = $statements[0]->expr->value;
        }
        else if($statements[0]->expr instanceof ClassConstFetch) {
            $resourceClassName = $statements[0]->expr->class->name;
        } else {
            return [];
        }

        $resourceClass = $this->broker->getClass($resourceClassName);
        $resourceClassAttributes = $resourceClass->getAttributes();
        foreach ($resourceClassAttributes as $attribute) {
            if ($attribute->getName() === 'Sylius\Resource\Metadata\AsResource') {
                return [];
            }
        }

        $message = sprintf(
            'getResourceClass() needs to provide a resource class. Mark "%s" with #[AsResource] attribute.',
            $resourceClass->getName()
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('sylius.grid.resourceClassRequired')
                ->build(),
        ];
    }
}
