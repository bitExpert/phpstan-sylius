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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;

/**
 * @implements Collector<StaticCall, array{string, string, int}>
 */
class CollectFieldsForGridClass extends AbstractGridClassCollector implements Collector
{
    /**
     * @return class-string
     */
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof StaticCall) {
            return null;
        }

        if ((!$node->name instanceof Identifier) || ('create' !== $node->name->toString())) {
            return null;
        }

        if (!$this->scopeIsAbstractGridSubclass($scope)) {
            return null;
        }

        if (!$this->isFieldInterfaceReturnType($scope->getType($node))) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return null;
        }
        $classType = new ObjectType($classReflection->getName());

        /** @var Arg $arg */
        $arg = $node->args[0];
        /** @var String_ $fieldName */
        $fieldName = $arg->value;

        return [$classType->getClassName(), $fieldName->value, $node->getLine()];
    }
}
