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

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;

interface FilterNode
{
    public function supports(FullyQualified $nodeClass): bool;

    /**
     * @return string[]
     */
    public function getFilterFields(StaticCall $node): array;
}
