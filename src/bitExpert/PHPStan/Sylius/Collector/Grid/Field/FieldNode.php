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

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;

interface FieldNode
{
    /** Returns true if this node handles the concrete Sylius field class. */
    public function supports(FullyQualified $nodeClass): bool;

    /**
     * Extracts the field name(s) from the StaticCall node.
     *
     * @return string[] list of field identifiers (already converted to camelCase)
     */
    public function getFieldNames(StaticCall $node): array;
}
