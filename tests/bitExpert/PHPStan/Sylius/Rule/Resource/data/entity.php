<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Model\ResourceInterface;

#[AsResource(
    formType: 'FormClassNotExists',
)]
class entity implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
