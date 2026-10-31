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

namespace bitExpert\PHPStan\Util;

use PHPStan\Type\Type;

/**
 * An attribute argument is not guaranteed to be a single constant string.
 * Calling Type::getValue() on anything else raises an internal PHPStan error
 * and aborts the whole analysis, so only single constant strings are accepted.
 */
final class ConstantString
{
    public static function fromType(?Type $type): ?string
    {
        if (null === $type) {
            return null;
        }

        $constantStrings = $type->getConstantStrings();

        return 1 === \count($constantStrings) ? $constantStrings[0]->getValue() : null;
    }
}
