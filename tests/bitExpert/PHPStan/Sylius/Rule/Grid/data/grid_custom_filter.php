<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Supplier;
use App\Filter\CustomFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\Filter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;

/**
 * The single filter call below is resolvable by two different nodes: the
 * catch-all Filter node, which matches CustomFilter because it implements
 * FilterInterface, and a dedicated node registered for CustomFilter by class
 * name. Whichever comes first in the registry wins, and the two disagree
 * about the field name, so the reported error identifies which one ran.
 *
 * The first argument is what the catch-all extracts; the dedicated node
 * returns a different field on purpose.
 */
final class grid_custom_filter extends AbstractGrid implements ResourceAwareGridInterface
{
    public static function getName(): string
    {
        return 'app_custom_filter';
    }

    public function getResourceClass(): string
    {
        return Supplier::class;
    }

    public function buildGrid(GridBuilderInterface $gridBuilder): void
    {
        // Sanity check that the production catch-all still resolves this call
        // on its own, with no dedicated node registered.
        $gridBuilder->addFilter(
            Filter::create('catchAllOnlyField', 'string'),
        );

        $gridBuilder->addFilter(
            CustomFilter::create('catchAllField', 'string'),
        );
    }
}
