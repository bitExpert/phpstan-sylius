<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Model\ResourceInterface;

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

    public function getId(): int
    {
        return $this->id;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getVatCode(): string
    {
        return 'Method without a property';
    }
}
