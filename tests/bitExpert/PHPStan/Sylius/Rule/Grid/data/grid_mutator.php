<?php

declare(strict_types=1);

namespace App\Grid\Mutator;

use App\Entity\Status;
use Sylius\Bundle\GridBundle\Builder\Field\CallableField;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Filter\BooleanFilter;
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
    final class AdminSupplierGridMutator implements GridMutatorInterface
    {
        public function __invoke(GridBuilderInterface $gridBuilder): void
        {
            $gridBuilder->addField(
                StringField::create('name')->setLabel('app.ui.name'),
            );
            $gridBuilder->addField(
                StringField::create('address.city')->setLabel('app.ui.address.city'),
            );
            $gridBuilder->addField(
                StringField::create('address.country.missingThirdSegment')->setLabel('app.ui.missing'),
            );
            $gridBuilder->addField(
                StringField::create('plainFieldAfterDottedField')->setLabel('app.ui.plain'),
            );
            $gridBuilder->addField(
                StringField::create('.')->setLabel('app.ui.some_calculated_field'),
            );
            $gridBuilder->addField(
                CallableField::createForService('missingMutatorCallableField', 'app.some_service'),
            );
            $gridBuilder->addFilter(
                StringFilter::create('missingMutatorFilterField'),
            );
            $gridBuilder->addFilter(
                EnumFilter::create('status', Status::class, false, 'status123'),
            );
            $gridBuilder->addFilter(
                StringFilter::create('virtual-field', ['name', 'address.city']),
            );
            $gridBuilder->addFilter(
                BooleanFilter::create('missing_mutator_boolean_filter'),
            );
        }
    }

    /*
     * A mutator for a grid name no analyzed grid declares. Without a resource
     * class to resolve to, its fields and filters are not validated.
     */
    #[AsGridMutator(grid: 'unknown_grid')]
    final class UnknownGridMutator implements GridMutatorInterface
    {
        public function __invoke(GridBuilderInterface $gridBuilder): void
        {
            $gridBuilder->addField(
                StringField::create('whatever')->setLabel('app.ui.whatever'),
            );
        }
    }

    /*
     * Not a grid mutator, so its field factory calls must be ignored even
     * though they look exactly like the ones inside a mutator.
     */
    final class NotAMutator
    {
        public function createMethod(): void
        {
            StringField::create('name')->setLabel('app.ui.name');
        }
    }
}
