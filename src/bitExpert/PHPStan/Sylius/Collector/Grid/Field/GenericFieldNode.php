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

namespace bitExpert\PHPStan\Sylius\Collector\Grid\Field;

use bitExpert\PHPStan\Util\PropertyName;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;

final readonly class GenericFieldNode implements FieldNode
{
    private const FIELD_TYPE = 'Sylius\\Bundle\\GridBundle\\Builder\\Field\\Field';

    public function supports(FullyQualified $nodeClass): bool
    {
        return self::FIELD_TYPE === $nodeClass->name;
    }

    public function getFieldNames(StaticCall $node): array
    {
        $fieldNames = [];

        if (isset($node->args[0])) {
            $arg = $node->args[0];
            if ($arg instanceof Arg && $arg->value instanceof String_) {
                $fieldNames[] = PropertyName::convertSnakeToCamelCase($arg->value->value);
            }
        }

        return $fieldNames;
    }
}
