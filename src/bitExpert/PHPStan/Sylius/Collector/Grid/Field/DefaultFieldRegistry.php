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

final readonly class DefaultFieldRegistry implements FieldRegistry
{
    /** @param FieldNode[] $fields */
    public function __construct(private readonly array $fields)
    {
    }

    /** @return FieldNode[] */
    public function getFields(): array
    {
        return $this->fields;
    }
}
