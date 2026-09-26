<?php

/*
 * This file is part of the phpstan-sylius package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace App\Filter;

use Sylius\Bundle\GridBundle\Builder\Filter\FilterInterface;

/**
 * Stands in for a user-supplied filter class that happens to implement
 * FilterInterface. The catch-all Filter node matches it by interface, so a
 * dedicated node for this class competes with the catch-all and only one of
 * them can win. That is what makes the registry order observable.
 *
 * The interface members are implemented for real rather than stubbed out so
 * that PHPStan verifies them: the 1.16 lane changes the legacy interface into
 * a sub-interface of the Component one, and a wrong signature here would be
 * reported on that lane only.
 */
class CustomFilter implements FilterInterface
{
    /** @var array<string, mixed> */
    private array $options = [];

    /** @var array<string, mixed> */
    private array $formOptions = [];

    public static function create(string $name, string $type): self
    {
        return new self();
    }

    public function getName(): string
    {
        return '';
    }

    public function getLabel(): string|bool|null
    {
        return null;
    }

    public function setLabel(string|bool|null $label): self
    {
        return $this;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function setEnabled(bool $enabled): self
    {
        return $this;
    }

    public function getTemplate(): ?string
    {
        return null;
    }

    public function setTemplate(?string $template): self
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function addOption(string $option, $value): self
    {
        $this->options[$option] = $value;

        return $this;
    }

    public function removeOption(string $option): self
    {
        unset($this->options[$option]);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormOptions(): array
    {
        return $this->formOptions;
    }

    /**
     * @param array<string, mixed> $formOptions
     */
    public function setFormOptions(array $formOptions): self
    {
        $this->formOptions = $formOptions;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function addFormOption(string $option, $value): self
    {
        $this->formOptions[$option] = $value;

        return $this;
    }

    public function removeFormOption(string $option): self
    {
        unset($this->formOptions[$option]);

        return $this;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function setCriteria(array $criteria): self
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
