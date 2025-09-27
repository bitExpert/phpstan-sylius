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

namespace bitExpert\PHPStan\Sylius\Collector\Grid;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Type\ObjectType;

/**
 * @implements Collector<MethodReturnStatementsNode, array{string, string, int}>
 */
class CollectRessourceClassForGridClass implements Collector
{
    /**
     * @return class-string
     */
    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof MethodReturnStatementsNode) {
            return null;
        }

        // run the checks only for subclasses of \Sylius\Bundle\GridBundle\Grid\AbstractGrid
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return null;
        }
        $parentType = new ObjectType('\Sylius\Bundle\GridBundle\Grid\AbstractGrid');
        $classType = new ObjectType($classReflection->getName());
        if (!$parentType->isSuperTypeOf($classType)->yes()) {
            return null;
        }

        // we are only interested in the getResourceClass() method
        $methodReflection = $node->getMethodReflection();
        if ('getResourceClass' !== $methodReflection->getName()) {
            return null;
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
        }

        if (!empty($resourceClassName)) {
            return [$classType->getClassName(), $resourceClassName, $node->getLine()];
        }

        return null;
    }
}
