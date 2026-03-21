<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Model\ResourceInterface;

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

class Address
{
    private $city;

    public function getCity(): string
    {
        return $this->city;
    }
}

class Supplier implements ResourceInterface
{
    private int $id;

    private Address $address;

    private Status $status;

    public function getId(): int
    {
        return $this->id;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getVatCode(): string
    {
        return 'Method without a property';
    }
}
