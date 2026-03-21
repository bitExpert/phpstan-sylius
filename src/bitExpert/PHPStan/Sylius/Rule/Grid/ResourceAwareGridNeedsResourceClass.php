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
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\ClassNotFoundException;
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

    /**
     * @return class-string
     */
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
        if (null === $classReflection) {
            return [];
        }

        $parentType = new ObjectType('\Sylius\Bundle\GridBundle\Grid\AbstractGrid');
        $classType = new ObjectType($classReflection->getName());
        if (!$parentType->isSuperTypeOf($classType)->yes()) {
            return [];
        }

        $resourceClassName = '';

        // new Resource Bundle logic: check the #AsGrid attribute of the class
        $attributes = $classReflection->getAttributes();
        foreach ($attributes as $attribute) {
            if ('Sylius\Component\Grid\Attribute\AsGrid' === $attribute->getName()) {
                $argumentTypes = $attribute->getArgumentTypes();
                if (isset($argumentTypes['resourceClass'])) {
                    $resourceClassName = $argumentTypes['resourceClass']->getConstantStrings()[0]->getValue();
                    break;
                }
            }
        }

        // old Resource Bundle logic: find the getResourceClass() method to get the resource class
        if (empty($resourceClassName)) {
            $methodReflection = $node->getMethodReflection();
            if ('getResourceClass' === $methodReflection->getName()) {
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
            }
        }

        if (empty($resourceClassName)) {
            return [];
        }

        try {
            $resourceClass = $this->broker->getClass($resourceClassName);
        } catch (ClassNotFoundException $e) {
            $message = \sprintf(
                'Resource class "%s" not found!',
                $resourceClassName,
            );

            return [
                RuleErrorBuilder::message($message)
                    ->identifier('sylius.grid.resourceClassRequired')
                    ->build(),
            ];
        }

        \error_log("6 OK\n", 3, '/tmp/sylius.log');

        return [];
    }
}
