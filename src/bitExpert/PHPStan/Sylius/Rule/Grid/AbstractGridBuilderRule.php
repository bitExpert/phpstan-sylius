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

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;

abstract readonly class AbstractGridBuilderRule
{
    public function __construct(protected ReflectionProvider $broker)
    {
    }

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

    protected function getResourceClassEntity(Scope $scope): ?ClassReflection
    {
        try {
            $classReflection = $scope->getClassReflection();
            if (null !== $classReflection) {
                $methodReflection = $classReflection->getNativeMethod('getResourceClass');
                $reflectionMethod = new \ReflectionMethod($classReflection->getName(), $methodReflection->getName());
                $resourceClass = (string) $reflectionMethod->invoke(new ($classReflection->getName()));
                return $this->broker->getClass($resourceClass);
            }
        } catch (MissingMethodFromReflectionException|\ReflectionException $e) {
        }

        return null;
    }
}
