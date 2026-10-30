<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use Sylius\Component\Grid\Attribute\AsGrid;

/*
 * A grid whose name is set explicitly in the AsGrid attribute. The mutator
 * has to reference that name, not the class name.
 */
#[AsGrid(resourceClass: Supplier::class, name: 'app_custom_grid_name')]
final class CustomNamedAttrGrid
{
}
