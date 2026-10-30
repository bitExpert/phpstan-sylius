<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Model\ResourceInterface;

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

class Country
{
    private string $isoCode;

    public function getIsoCode(): string
    {
        return $this->isoCode;
    }
}

class Address
{
    private $city;

    private Country $country;

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): Country
    {
        return $this->country;
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

class Patient
{
    private string $name;

    public function getName(): string
    {
        return $this->name;
    }
}

class Medipack implements ResourceInterface
{
    private Patient $patient;

    private $untyped;

    public function getId(): int
    {
        return 0;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function getUntyped()
    {
        return $this->untyped;
    }
}
