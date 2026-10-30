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

namespace bitExpert\PHPStan\Sylius\Rule\Grid\GridMutator;

use bitExpert\PHPStan\Sylius\Collector\Grid\GridMutator\CollectFields;
use bitExpert\PHPStan\Sylius\Rule\Grid\AbstractGridBuilderResourceRule;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;

/**
 * Validates the fields a grid mutator adds to the grid builder against the
 * resource class of the grid the mutator is attached to. The mutator is
 * linked to the grid via the grid name in #[AsGridMutator(grid: '...')],
 * which is treated as unique (in practice the grid class name is used).
 */
final readonly class FieldIsPartOfResourceClass extends AbstractGridBuilderResourceRule
{
    public function __construct(ReflectionProvider $broker)
    {
        parent::__construct($broker, CollectFields::class);
    }

    protected function resolveResourceMap(CollectedDataNode $node): array
    {
        return $this->resolveMutatorResourceMap($node);
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
        if (!\is_string($item)) {
            return [];
        }

        return $this->validateFieldItem(
            $ownerClassName,
            $resourceClassName,
            $item,
            $lineNo,
            $file,
            $scope,
            'sylius.grid.mutatorResourceClassMissingProperty',
            'sylius.grid.mutatorResourceClassPropertyMissingType',
        );
    }
}
