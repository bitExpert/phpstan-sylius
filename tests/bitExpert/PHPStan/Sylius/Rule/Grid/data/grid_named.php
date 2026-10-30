<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;

/*
 * A grid that is referenced by name from a grid mutator. The grid name is
 * the grid class name, which is the convention grid mutators rely on.
 */
final class AdminSupplierNamedGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    public static function getName(): string
    {
        return 'App\Grid\AdminSupplierNamedGrid';
    }

    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
    }

    public function getResourceClass(): string
    {
        return Supplier::class;
    }
}
