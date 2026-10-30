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

namespace bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilder;

use bitExpert\PHPStan\Sylius\Collector\Grid\GridBuilder\CollectFilter;
use bitExpert\PHPStan\Sylius\Rule\Grid\AbstractGridBuilderResourceRule;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;

final readonly class FilterIsPartOfResourceClass extends AbstractGridBuilderResourceRule
{
    public function __construct(ReflectionProvider $broker)
    {
        parent::__construct($broker, CollectFilter::class);
    }

    protected function resolveResourceMap(CollectedDataNode $node): array
    {
        return $this->resolveGridResourceMap($node);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    protected function validateItem(
        string $ownerClassName,
        string $resourceClassName,
        mixed $item,
        int $lineNo,
        string $file,
        Scope $scope,
    ): array {
        if (!\is_array($item)) {
            return [];
        }

        return $this->validateFilterItem(
            $ownerClassName,
            $resourceClassName,
            $item,
            $lineNo,
            $file,
            'sylius.grid.resourceClassMissingFilter',
        );
    }
}
