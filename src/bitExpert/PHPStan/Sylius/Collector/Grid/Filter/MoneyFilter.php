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
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;

final readonly class MoneyFilter implements FilterNode
{
    private const FILTER_TYPE = 'Sylius\\Bundle\\GridBundle\\Builder\\Filter\\MoneyFilter';

    public function supports(FullyQualified $nodeClass): bool
    {
        return self::FILTER_TYPE === $nodeClass->name;
    }

    public function getFilterFields(StaticCall $node): array
    {
        $filterFields = [];

        // create(string $name, string $currencyCode, ?int $scale = null):
        // the currency code is the second argument, the field is the first one.
        if (isset($node->args[0])) {
            $arg = $node->args[0];
            if ($arg instanceof Arg && $arg->value instanceof String_) {
                $filterFields[] = PropertyName::convertSnakeToCamelCase($arg->value->value);
            }
        }

        return $filterFields;
    }
}
