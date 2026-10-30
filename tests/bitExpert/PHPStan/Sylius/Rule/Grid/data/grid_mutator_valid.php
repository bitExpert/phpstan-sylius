<?php

declare(strict_types=1);

namespace App\Grid\Mutator;

use App\Entity\Status;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Filter\EnumFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Component\Grid\Attribute\AsGridMutator;
use Sylius\Component\Grid\Mutator\GridMutatorInterface;

/*
 * GridMutatorInterface and the AsGridMutator attribute only exist on
 * grid-bundle >= 1.16, and this file is registered in composer's autoload-dev
 * files, so the declarations are guarded to keep the older lane from
 * fataling on unknown classes.
 */
if (\interface_exists(GridMutatorInterface::class)) {
    #[AsGridMutator(grid: 'App\Grid\AdminSupplierNamedGrid')]
    final class AdminSupplierGridMutatorValid implements GridMutatorInterface
    {
        public function __invoke(GridBuilderInterface $gridBuilder): void
        {
            $gridBuilder->addField(
                StringField::create('id')->setLabel('app.ui.id'),
            );
            $gridBuilder->addField(
                StringField::create('vatCode')->setLabel('app.ui.vatCode'),
            );
            $gridBuilder->addField(
                StringField::create('address.city')->setLabel('app.ui.address.city'),
            );
            $gridBuilder->addField(
                StringField::create('address.country.isoCode')->setLabel('app.ui.isoCode'),
            );
            $gridBuilder->addFilter(
                StringFilter::create('id'),
            );
            $gridBuilder->addFilter(
                EnumFilter::create('status', Status::class),
            );
        }
    }
}
