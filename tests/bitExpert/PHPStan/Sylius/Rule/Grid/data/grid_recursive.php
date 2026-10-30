<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Medipack;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Grid\AbstractGrid;
use Sylius\Bundle\GridBundle\Grid\ResourceAwareGridInterface;

final class AdminMedipackGrid extends AbstractGrid implements ResourceAwareGridInterface
{
    public static function getName(): string
    {
        return 'app_admin_medipack';
    }

    public function buildGrid(\Sylius\Bundle\GridBundle\Builder\GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder->addField(
            StringField::create('patient.name')->setLabel('patient.name'),
        );
        $gridBuilder->addField(
            StringField::create('patient.unknown')->setLabel('patient.unknown'),
        );
        $gridBuilder->addField(
            StringField::create('untyped.name')->setLabel('untyped.name'),
        );
        $gridBuilder->addField(
            StringField::create('missingRoot.child')->setLabel('missingRoot.child'),
        );
    }

    public function getResourceClass(): string
    {
        return Medipack::class;
    }
}
