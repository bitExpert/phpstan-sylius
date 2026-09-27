<?php

declare(strict_types=1);

namespace App\Entity;

use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Attribute arguments are not guaranteed to be a single constant string. The
 * rules used to call Type::getValue() unconditionally, which raised an internal
 * error for an ObjectType and a ConstantArrayType and aborted the whole
 * analysis. All of these must now be skipped silently instead.
 */
#[AsResource(formType: new \stdClass())]
class EntityWithNonConstantFormType implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}

#[AsResource(formType: [self::class])]
class EntityWithArrayFormType implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}

#[AsResource(formType: 123)]
class EntityWithIntFormType implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}

#[Index(grid: new \stdClass())]
class EntityWithNonConstantGrid implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}

#[Index(grid: [self::class])]
class EntityWithArrayGrid implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}

/**
 * A single constant string is still resolved, so a missing class is reported.
 */
#[AsResource(formType: 'FormClassNotExists')]
class EntityWithConstantFormType implements ResourceInterface
{
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
