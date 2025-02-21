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
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * @implements Rule<MethodReturnStatementsNode>
 */
readonly class ResourceAwareGridNeedsResourceClass implements Rule
{
    public function __construct(private ReflectionProvider $broker)
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
        if ($classReflection === null) {
            return [];
        }
        $parentType = new ObjectType('\Sylius\Bundle\GridBundle\Grid\AbstractGrid');
        $classType = new ObjectType($classReflection->getName());
        if (!$parentType->isSuperTypeOf($classType)->yes()) {
            return [];
        }

        // we are only interested in the getResourceClass() method
        $methodReflection = $node->getMethodReflection();
        if ('getResourceClass' !== $methodReflection->getName()) {
            return [];
        }

        $resourceClassName = '';
        /** @var Return_[] $statements */
        $statements = $node->getStatements();
        if ($statements[0]->expr instanceof String_) {
            $resourceClassName = $statements[0]->expr->value;
        } elseif ($statements[0]->expr instanceof ClassConstFetch) {
            /** @var FullyQualified $class */
            $class = $statements[0]->expr->class;
            $resourceClassName = $class->name;
        } else {
            return [];
        }

        $resourceClass = $this->broker->getClass($resourceClassName);
        $resourceClassAttributes = $resourceClass->getAttributes();
        foreach ($resourceClassAttributes as $attribute) {
            if ('Sylius\Resource\Metadata\AsResource' === $attribute->getName()) {
                return [];
            }
        }

        $message = \sprintf(
            'getResourceClass() needs to provide a resource class. Mark "%s" with #[AsResource] attribute.',
            $resourceClass->getName(),
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('sylius.grid.resourceClassRequired')
                ->build(),
        ];
    }
}
