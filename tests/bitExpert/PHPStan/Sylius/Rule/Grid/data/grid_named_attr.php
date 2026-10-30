<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use Sylius\Component\Grid\Attribute\AsGrid;

/*
 * A grid declared purely by the AsGrid attribute — the shape grid-bundle 1.16
 * prefers. The grid name defaults to the grid class name (see the
 * InvokableGridPass), which is what the mutator references.
 */
#[AsGrid(resourceClass: Supplier::class)]
final class AttributeOnlyNamedGrid
{
}
