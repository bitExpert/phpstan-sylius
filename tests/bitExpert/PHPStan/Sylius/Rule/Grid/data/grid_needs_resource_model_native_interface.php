<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\GridInterface;

/*
 * A grid in the shape grid-bundle 1.16 prefers: it implements
 * Sylius\Component\Grid\GridInterface and does not extend the legacy
 * Sylius\Bundle\GridBundle\Grid\AbstractGrid.
 *
 * The rule used to require AbstractGrid before it would even look at the
 * attribute, so the resource class of such a grid was never validated. Sylius
 * deprecated the legacy grid interface with "will be removed in 2.0", which makes
 * this the forward-looking case rather than an exotic one.
 *
 * Sylius\Component\Grid\GridInterface only exists on grid-bundle >= 1.16, and
 * this file is registered in composer's autoload-dev files, so the declarations
 * are guarded to keep the older lane from fataling on an unknown interface.
 */
if (\interface_exists(GridInterface::class)) {
    #[AsGrid(resourceClass: Supplier::class)]
    final class NativeGridWithExistingResourceClass implements GridInterface
    {
        public function getName(): string
        {
            return 'app_admin_supplier';
        }
    }

    #[AsGrid(resourceClass: 'App\Entity\SupplierNotFound')]
    final class NativeGridWithMissingResourceClass implements GridInterface
    {
        public function getName(): string
        {
            return 'app_admin_supplier_no_class';
        }
    }
}
