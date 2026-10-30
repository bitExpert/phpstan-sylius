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

namespace bitExpert\PHPStan\Sylius\Collector\Grid\GridMutator;

use bitExpert\PHPStan\Sylius\Collector\Grid\AbstractGridBuilderFieldCollector;
use PHPStan\Analyser\Scope;

/**
 * Collects the field factory calls a grid mutator adds to the grid builder.
 */
final class CollectFields extends AbstractGridBuilderFieldCollector
{
    protected function scopeIsBuilderScope(Scope $scope): bool
    {
        return $this->scopeIsGridMutator($scope);
    }
}
