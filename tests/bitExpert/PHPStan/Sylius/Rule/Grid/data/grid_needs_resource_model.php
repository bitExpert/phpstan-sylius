<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use App\Entity\SupplierNotFound;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;

final class GridWithExistingResourceClass extends AbstractGrid implements ResourceAwareGridInterface
{
    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
    }

    public static function getName(): string
    {
        return 'app_admin_supplier';
    }

    public function getResourceClass(): string
    {
        return Supplier::class;
    }
}

final class GridWithResourceClassNotFound extends AbstractGrid implements ResourceAwareGridInterface
{
    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
    }

    public static function getName(): string
    {
        return 'app_admin_supplier_no_class';
    }

    public function getResourceClass(): string
    {
        return SupplierNotFound::class;
    }
}
