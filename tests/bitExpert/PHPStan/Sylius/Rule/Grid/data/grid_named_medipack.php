<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Medipack;
use Sylius\Component\Grid\Attribute\AsGrid;

/*
 * A second grid the repeatable mutator is attached to, with a different
 * resource class so the two joins are distinguishable.
 */
#[AsGrid(resourceClass: Medipack::class)]
final class MedipackGrid
{
}
