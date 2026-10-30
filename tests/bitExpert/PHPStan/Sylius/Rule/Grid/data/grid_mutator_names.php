<?php

declare(strict_types=1);

namespace App\Grid\Mutator;

use Sylius\Bundle\GridBundle\Builder\Field\StringField;
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
     * References the attribute-only grid by its class name, which is the
     * runtime default name of an #[AsGrid] grid without a name: argument.
     */
    #[AsGridMutator(grid: 'App\Grid\AttributeOnlyNamedGrid')]
    final class AttributeOnlyGridMutator implements GridMutatorInterface
    {
        public function __invoke(GridBuilderInterface $gridBuilder): void
        {
            $gridBuilder->addField(
                StringField::create('name')->setLabel('app.ui.name'),
            );
        }
    }

    /*
     * References the explicitly named grid by its AsGrid name.
     */
    #[AsGridMutator(grid: 'app_custom_grid_name')]
    final class CustomNamedGridMutator implements GridMutatorInterface
    {
        public function __invoke(GridBuilderInterface $gridBuilder): void
        {
            $gridBuilder->addField(
                StringField::create('vatCode')->setLabel('app.ui.vatCode'),
            );
            $gridBuilder->addField(
                StringField::create('missingCustomNamedField')->setLabel('app.ui.missing'),
            );
        }
    }
}
