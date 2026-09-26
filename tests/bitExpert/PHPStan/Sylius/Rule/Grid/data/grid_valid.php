<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Status;
use App\Entity\Supplier;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Filter\EnumFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;

final class grid_valid extends AbstractGrid implements ResourceAwareGridInterface
{
    public static function getName(): string
    {
        return 'app_admin_valid_supplier';
    }

    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder->addField(
            StringField::create('id')->setLabel('app.ui.id'),
        );
        $gridBuilder->addField(
            StringField::create('name')->setLabel('app.ui.name'),
        );
        $gridBuilder->addField(
            StringField::create('address.city')->setLabel('app.ui.address.city'),
        );
        $gridBuilder->addField(
            StringField::create('.')->setLabel('app.ui.some_calculated_field'),
        );
        $gridBuilder->addFilter(
            StringFilter::create('name'),
        );
        $gridBuilder->addFilter(
            EnumFilter::create('status', Status::class, false, 'status'),
        );
        $gridBuilder->addFilter(
            StringFilter::create('virtual-field', ['name', 'address.city']),
        );
    }

    public function getResourceClass(): string
    {
        return Supplier::class;
    }
}
