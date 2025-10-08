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
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;

/**
 * @implements Collector<StaticCall, array{string, array{string}, int}>
 */
class CollectFilterForGridClass extends AbstractGridClassCollector implements Collector
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

        if (!$this->isFilterInterfaceReturnType($scope->getType($node))) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return null;
        }
        $classType = new ObjectType($classReflection->getName());

        // first check if the various filter implementations have defined custom fields to filter on
        $filterFields = [];
        /** @var FullyQualified $nodeClass */
        $nodeClass = $node->class;
        if ('Sylius\\Bundle\\GridBundle\\Builder\\Filter\\StringFilter' === $nodeClass->name) {
            if (isset($node->args[1]) && $node->args[1] instanceof Arg) {
                /** @var Array_ $array */
                $array = $node->args[1]->value;
                foreach ($array->items as $item) {
                    /** @var String_ $value */
                    $value = $item->value;
                    $filterFields[] = $this->convertSnakeToCamelCase($value->value);
                }
            }
        } elseif ('Sylius\\Bundle\\GridBundle\\Builder\\Filter\\SelectFilter' === $nodeClass->name) {
            if (isset($node->args[3])) {
                /** @var Arg $arg */
                $arg = $node->args[3];
                /** @var String_ $value */
                $value = $arg->value;
                $filterFields[] = $this->convertSnakeToCamelCase($value->value);
            }
        } elseif ('Sylius\\Bundle\\GridBundle\\Builder\\Filter\\ExistsFilter' === $nodeClass->name) {
            if (isset($node->args[1])) {
                /** @var Arg $arg */
                $arg = $node->args[1];
                /** @var String_ $value */
                $value = $arg->value;
                $filterFields[] = $this->convertSnakeToCamelCase($value->value);
            }
        } elseif ('Sylius\\Bundle\\GridBundle\\Builder\\Filter\\EntityFilter' === $nodeClass->name) {
            if (isset($node->args[3]) && $node->args[3] instanceof Arg) {
                /** @var Array_ $array */
                $array = $node->args[3]->value;
                foreach ($array->items as $item) {
                    /** @var String_ $value */
                    $value = $item->value;
                    $filterFields[] = $this->convertSnakeToCamelCase($value->value);
                }
            }
        }

        // if no $filterFields have been found, we fallback to the first parameter passed to the create() method
        if ((0 === \count($filterFields)) && isset($node->args[0])) {
            /** @var Arg $arg */
            $arg = $node->args[0];
            /** @var String_ $value */
            $value = $arg->value;
            $filterFields[] = $this->convertSnakeToCamelCase($value->value);
        }

        if (0 === \count($filterFields)) {
            return null;
        }

        return [$classType->getClassName(), $filterFields, $node->getLine()];
    }
}
