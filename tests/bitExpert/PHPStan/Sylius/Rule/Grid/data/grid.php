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
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Filter\Filter;
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
        $gridBuilder->addField(
            StringField::create('id')->setLabel('app.ui.id'),
        );
        $gridBuilder->addField(
            StringField::create('name')->setLabel('app.ui.name'),
        );
        $gridBuilder->addField(
            StringField::create('.')->setLabel('app.ui.some_calculated_field'),
        );
        $gridBuilder->addFilter(
            Filter::create('name', 'string'),
        );
    }

    public function getResourceClass(): string
    {
        return Supplier::class;
    }
}

final class SomeOtherClass
{
    public function createMethod(): void
    {
        StringField::create('name')->setLabel('app.ui.name');
    }

    public function getResourceClass(): string
    {
        return self::class;
    }
}
