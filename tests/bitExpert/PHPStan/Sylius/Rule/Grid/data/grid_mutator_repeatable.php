<?php

declare(strict_types=1);

namespace App\Grid\Mutator;

use Sylius\Bundle\GridBundle\Builder\Field\StringField;
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
    /*
     * The attribute is repeatable: at runtime the mutator is registered for
     * both grids, so its fields are validated against both resource classes.
     */
    #[AsGridMutator(grid: 'App\Grid\AttributeOnlyNamedGrid')]
    #[AsGridMutator(grid: 'App\Grid\MedipackGrid')]
    final class RepeatableGridMutator implements GridMutatorInterface
    {
        public function __invoke(GridBuilderInterface $gridBuilder): void
        {
            // missing on App\Entity\Supplier, existing on App\Entity\Medipack
            $gridBuilder->addField(
                StringField::create('patient')->setLabel('app.ui.patient'),
            );
            // missing on both resource classes
            $gridBuilder->addField(
                StringField::create('missingOnBoth')->setLabel('app.ui.missing'),
            );
            $gridBuilder->addFilter(
                StringFilter::create('missingFilterOnBoth'),
            );
        }
    }
}
