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

namespace bitExpert\PHPStan\Sylius\Rule\Grid\GridMutator;

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectGridNameForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\CallableFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DateTimeFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DefaultFieldRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\EnumFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\GenericFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\StringFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\TwigFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\GridMutator\CollectFields;
use bitExpert\PHPStan\Sylius\Collector\Grid\GridMutator\CollectGridNames;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FieldIsPartOfResourceClass>
 */
class FieldIsPartOfResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FieldIsPartOfResourceClass($this->createReflectionProvider());
    }

    protected function getCollectors(): array
    {
        $fields = [];
        $fields[] = new StringFieldNode();
        $fields[] = new DateTimeFieldNode();
        $fields[] = new TwigFieldNode();
        $fields[] = new EnumFieldNode();
        $fields[] = new CallableFieldNode();
        $fields[] = new GenericFieldNode();

        // Mirrors the registration order in extension.neon.
        return [
            new CollectRessourceClassForGridClass(),
            new CollectGridNameForGridClass(),
            new CollectGridNames(),
            new CollectFields(new DefaultFieldRegistry($fields)),
        ];
    }

    public function testRule(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\Mutator\GridMutatorInterface')) {
            self::markTestSkipped('GridMutatorInterface requires grid-bundle >= 1.16.');
        }

        // The named grid has to be analyzed alongside the mutator: the mutator
        // is linked to the resource class via mutator -> grid name -> grid.
        $this->analyse(
            [__DIR__ . '/../data/grid_named.php', __DIR__ . '/../data/grid_mutator.php'],
            [
                [
                    'The field "name" needs to exists as property in class "App\Entity\Supplier".',
                    30,
                ],
                [
                    // Third segment of a recursive field. The first two segments
                    // ("address", "country") resolve to App\Entity\Country, so the
                    // missing third segment is looked up on — and reported
                    // against — that class, not the grid's root resource class.
                    'The field "missingThirdSegment" needs to exists as property in class "App\Entity\Country".',
                    36,
                ],
                [
                    // Declared after the recursive "address.country.missingThirdSegment"
                    // field. Walking the recursive path replaced the resource class
                    // with the type of the last resolved segment, so the field is
                    // re-resolved against App\Entity\Supplier and reported there.
                    'The field "plainFieldAfterDottedField" needs to exists as property in class "App\Entity\Supplier".',
                    39,
                ],
                [
                    'The field "missingMutatorCallableField" needs to exists as property in class "App\Entity\Supplier".',
                    45,
                ],
            ],
        );
    }

    /**
     * The grid name of an #[AsGrid] grid defaults to the grid class name (the
     * InvokableGridPass convention), so an attribute-only grid without any
     * getName() method is joined through its class name.
     */
    public function testRuleJoinsAttributeOnlyGridByClassName(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\Mutator\GridMutatorInterface')) {
            self::markTestSkipped('GridMutatorInterface requires grid-bundle >= 1.16.');
        }

        $this->analyse(
            [__DIR__ . '/../data/grid_named_attr.php', __DIR__ . '/../data/grid_mutator_names.php'],
            [
                [
                    'The field "name" needs to exists as property in class "App\Entity\Supplier".',
                    29,
                ],
            ],
        );
    }

    /**
     * An explicit #[AsGrid(name: '...')] wins over the class-name default, so
     * the mutator has to reference that name.
     */
    public function testRuleUsesAsGridNameArgumentWhenPresent(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\Mutator\GridMutatorInterface')) {
            self::markTestSkipped('GridMutatorInterface requires grid-bundle >= 1.16.');
        }

        $this->analyse(
            [__DIR__ . '/../data/grid_named_custom.php', __DIR__ . '/../data/grid_mutator_names.php'],
            [
                [
                    'The field "missingCustomNamedField" needs to exists as property in class "App\Entity\Supplier".',
                    46,
                ],
            ],
        );
    }

    /**
     * AsGridMutator is repeatable: one mutator attached to two grids mutates
     * both, so every field is validated against both resource classes. The
     * "patient" field exists on Medipack but not on Supplier, so it is only
     * reported for the Supplier join; "missingOnBoth" is reported twice, once
     * per joined grid.
     */
    public function testRuleValidatesRepeatableMutatorAgainstEveryGrid(): void
    {
        if (!\interface_exists('Sylius\Component\Grid\Mutator\GridMutatorInterface')) {
            self::markTestSkipped('GridMutatorInterface requires grid-bundle >= 1.16.');
        }

        $this->analyse(
            [
                __DIR__ . '/../data/grid_named_attr.php',
                __DIR__ . '/../data/grid_named_medipack.php',
                __DIR__ . '/../data/grid_mutator_repeatable.php',
            ],
            [
                [
                    'The field "patient" needs to exists as property in class "App\Entity\Supplier".',
                    32,
                ],
                [
                    'The field "missingOnBoth" needs to exists as property in class "App\Entity\Medipack".',
                    36,
                ],
                [
                    'The field "missingOnBoth" needs to exists as property in class "App\Entity\Supplier".',
                    36,
                ],
            ],
        );
    }
}
