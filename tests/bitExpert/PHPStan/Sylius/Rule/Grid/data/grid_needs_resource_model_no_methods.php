<?php

declare(strict_types=1);

namespace App\Grid;

use Sylius\Component\Grid\Attribute\AsGrid;

/**
 * The attribute is the whole declaration here: the class has no methods.
 *
 * The rule used to listen on MethodReturnStatementsNode, which is emitted once
 * per method, so a class with no methods was never visited and its resource
 * class was never validated. A synthetic minimal case, but exactly the shape
 * that broke.
 */
#[AsGrid(resourceClass: 'App\Entity\SupplierNotFound')]
final class GridNeedsResourceModelNoMethods
{
}
