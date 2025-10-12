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

class PropertyName
{
    public static function convertSnakeToCamelCase(string $string): string
    {
        if (!\str_contains($string, '_')) {
            return $string;
        }

        $parts = \explode('_', \strtolower($string));
        $camel = \array_shift($parts);
        $camel .= \implode('', \array_map('ucfirst', $parts));

        return $camel;
    }
}
