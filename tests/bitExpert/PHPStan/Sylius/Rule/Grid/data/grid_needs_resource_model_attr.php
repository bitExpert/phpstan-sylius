<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use App\Entity\SupplierNotFound;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Component\Grid\Attribute\AsGrid;

#[AsGrid(resourceClass: Supplier::class)]
final class GridAttrWithExistingResourceClass extends AbstractGrid
{
    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
    }
}

#[AsGrid(resourceClass: SupplierNotFound::class)]
final class GridAttrWithResourceClassNotFound extends AbstractGrid
{
    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
    }
}
