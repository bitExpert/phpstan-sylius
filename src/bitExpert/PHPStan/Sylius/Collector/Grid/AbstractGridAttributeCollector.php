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

use bitExpert\PHPStan\Util\ConstantString;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Type;

/**
 * Base class for collectors that need to extract attribute arguments
 * from grid or grid mutator classes.
 */
abstract class AbstractGridAttributeCollector extends AbstractGridClassCollector
{
    /**
     * Yields the argument types of every attribute with the given name on the class.
     *
     * @return iterable<array<string|int, Type>>
     */
    protected function getAttributeArgumentTypes(ClassReflection $classReflection, string $attributeName): iterable
    {
        foreach ($classReflection->getAttributes() as $attribute) {
            if ($attributeName !== $attribute->getName()) {
                continue;
            }

            yield $attribute->getArgumentTypes();
        }
    }

    /**
     * Extracts a constant string value from a type, returning null if the type
     * does not resolve to exactly one constant string.
     */
    protected static function resolveConstantString(?Type $type): ?string
    {
        return ConstantString::fromType($type);
    }
}
