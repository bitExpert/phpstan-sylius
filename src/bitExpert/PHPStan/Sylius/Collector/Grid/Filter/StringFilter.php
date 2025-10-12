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

namespace bitExpert\PHPStan\Sylius\Collector\Grid\Filter;

use bitExpert\PHPStan\Util\PropertyName;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;

final readonly class StringFilter implements FilterNode
{
    private const FILTER_TYPE = 'Sylius\\Bundle\\GridBundle\\Builder\\Filter\\StringFilter';

    public function supports(FullyQualified $nodeClass): bool
    {
        return self::FILTER_TYPE === $nodeClass->name;
    }

    public function getFilterFields(StaticCall $node): array
    {
        $filterFields = [];

        if (isset($node->args[1]) && $node->args[1] instanceof Arg) {
            $arg = $node->args[1];
            $array = $arg->value;
            if ($array instanceof Array_) {
                foreach ($array->items as $item) {
                    $value = $item->value;
                    if ($value instanceof String_) {
                        $filterFields[] = PropertyName::convertSnakeToCamelCase($value->value);
                    }
                }
            }
        }

        // if no $filterFields have been found, we fallback to the first parameter passed to the create() method
        if ((0 === \count($filterFields)) && isset($node->args[0])) {
            $arg = $node->args[0];
            if ($arg instanceof Arg && $arg->value instanceof String_) {
                $filterFields[] = PropertyName::convertSnakeToCamelCase($arg->value->value);
            }
        }

        return $filterFields;
    }
}
