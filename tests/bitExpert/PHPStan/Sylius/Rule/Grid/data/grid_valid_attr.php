<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Component\Grid\Attribute\AsGrid;

#[AsGrid(resourceClass: Supplier::class)]
final class GridValidAttr extends AbstractGrid
{
    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
    }
}
