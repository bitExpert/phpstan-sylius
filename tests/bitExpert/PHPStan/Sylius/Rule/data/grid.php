<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Model\ResourceInterface;

class Supplier implements ResourceInterface
{
    private int $id;

    public function getId()
    {
        return $this->id;
    }
}

namespace App\Grid;

use App\Entity\Supplier;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;

final class AdminSupplierGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    public static function getName(): string
    {
        return 'app_admin_supplier';
    }

    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
    }

    public function getResourceClass(): string
    {
        return Supplier::class;
    }
}
