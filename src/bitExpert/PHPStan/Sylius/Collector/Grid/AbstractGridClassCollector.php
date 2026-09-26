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
    protected function scopeIsGrid(Scope $scope): bool
    {
        try {
            // new Grid Bundle logic: Grid classes are marked with #AsGrid attribute
            $classReflection = $scope->getClassReflection();
            if (null !== $classReflection) {
                $attributes = $classReflection->getAttributes();
                foreach ($attributes as $attribute) {
                    if ('Sylius\Component\Grid\Attribute\AsGrid' === $attribute->getName()) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            // old Grid Bundle logic: check for subclasses of \Sylius\Bundle\GridBundle\Grid\AbstractGrid
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

    protected function isSubtypeOf(Type $type, string $superType): bool
    {
        try {
            $expectedReturnType = new ObjectType($superType);

            return $expectedReturnType->isSuperTypeOf($type)->yes();
        } catch (\Throwable $e) {
        }

        return false;
    }
}
