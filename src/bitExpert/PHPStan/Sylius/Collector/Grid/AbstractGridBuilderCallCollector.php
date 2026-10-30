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
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;

/**
 * Shared skeleton for the collectors that pick up field/filter factory calls
 * (StringField::create(), StringFilter::create(), ...) made on a grid builder,
 * either inside a grid class or inside a grid mutator.
 *
 * @implements Collector<StaticCall, array{0: string, 1: mixed, 2: int}>
 */
abstract class AbstractGridBuilderCallCollector extends AbstractGridClassCollector implements Collector
{
    /**
     * @return class-string<StaticCall>
     */
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @return array{0: string, 1: mixed, 2: int}|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof StaticCall) {
            return null;
        }

        if (!$this->isBuilderCall($node, $scope)) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return null;
        }

        $classType = new ObjectType($classReflection->getName());

        return $this->collect($node, $classType->getClassName());
    }

    /**
     * Whether the static call is a supported field/filter factory call made in
     * the scope of a grid or a grid mutator.
     */
    abstract protected function isBuilderCall(StaticCall $node, Scope $scope): bool;

    /**
     * Extracts the collected tuple from a supported factory call.
     *
     * @return array{0: string, 1: mixed, 2: int}|null
     */
    abstract protected function collect(StaticCall $node, string $className): ?array;
}
