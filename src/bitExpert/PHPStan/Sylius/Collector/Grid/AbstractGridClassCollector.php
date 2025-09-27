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

use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

abstract class AbstractGridClassCollector
{
    protected function scopeIsAbstractGridSubclass(Scope $scope): bool
    {
        try {
            // run the checks only for subclasses of \Sylius\Bundle\GridBundle\Grid\AbstractGrid
            $classReflection = $scope->getClassReflection();
            if (null !== $classReflection) {
                $parentType = new ObjectType('\Sylius\Bundle\GridBundle\Grid\AbstractGrid');
                $classType = new ObjectType($classReflection->getName());

                return $parentType->isSuperTypeOf($classType)->yes();
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    protected function isFieldInterfaceReturnType(Type $type): bool
    {
        return $this->isSuperTypeOf($type, '\Sylius\Bundle\GridBundle\Builder\Field\FieldInterface');
    }

    protected function isFilterInterfaceReturnType(Type $type): bool
    {
        return $this->isSuperTypeOf($type, '\Sylius\Bundle\GridBundle\Builder\Filter\FilterInterface');
    }

    private function isSuperTypeOf(Type $type, string $superType): bool
    {
        try {
            $expectedReturnType = new ObjectType($superType);

            return $expectedReturnType->isSuperTypeOf($type)->yes();
        } catch (\Throwable $e) {
        }

        return false;
    }
}
