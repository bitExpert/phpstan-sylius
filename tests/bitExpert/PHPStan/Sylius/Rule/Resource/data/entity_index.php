<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Model\ResourceInterface;

#[Index(grid: 'App\Grid\GridClassNotExists')]
class EntityIndex implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
