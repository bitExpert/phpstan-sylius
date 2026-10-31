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

namespace bitExpert\PHPStan\Sylius\Rule\Resource;

/**
 * Validates the grid class referenced by #[Index(grid: '...')] on a resource
 * class: the referenced class has to exist.
 */
class IndexOperationNeedsGridClassRule extends AbstractResourceAttributeClassRule
{
    protected function getAttributeName(): string
    {
        return 'Sylius\Resource\Metadata\Index';
    }

    protected function getArgumentName(): string
    {
        return 'grid';
    }

    protected function getNotFoundMessageTemplate(): string
    {
        return 'Grid class "%s" not found!';
    }

    protected function getNotFoundIdentifier(): string
    {
        return 'sylius.resource.gridClassNotFound';
    }
}
