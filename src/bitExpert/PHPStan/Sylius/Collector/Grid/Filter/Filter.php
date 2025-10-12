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
use PHPStan\Type\ObjectType;

final readonly class Filter implements FilterNode
{
    private const FILTER_TYPE = 'Sylius\\Bundle\\GridBundle\\Builder\\Filter\\FilterInterface';

    public function supports(FullyQualified $nodeClass): bool
    {
        try {
            $filterType = new ObjectType(self::FILTER_TYPE);
            $nodeClassType = new ObjectType($nodeClass->toString());

            return $nodeClassType->isSuperTypeOf($filterType)->yes();
        } catch (\Throwable $e) {
        }

        return false;
    }

    public function getFilterFields(StaticCall $node): array
    {
        $filterFields = [];

        if (isset($node->args[0])) {
            $arg = $node->args[0];
            if ($arg instanceof Arg && $arg->value instanceof String_) {
                $filterFields[] = PropertyName::convertSnakeToCamelCase($arg->value->value);
            }
        }

        return $filterFields;
    }
}
