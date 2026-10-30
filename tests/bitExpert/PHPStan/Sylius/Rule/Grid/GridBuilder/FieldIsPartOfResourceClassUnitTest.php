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

namespace bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilder;

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\CallableFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DateTimeFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DefaultFieldRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\EnumFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\GenericFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\StringFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\TwigFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\GridBuilder\CollectFields;
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

        return [
            new CollectRessourceClassForGridClass(),
            new CollectFields(new DefaultFieldRegistry($fields)),
        ];
    }

    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/grid.php'],
            [
                [
                    'The field "name" needs to exists as property in class "App\Entity\Supplier".',
                    34,
                ],
                [
                    // This field is declared after the recursive "address.city" field.
                    // Walking the recursive path replaced the resource class with the
                    // type of the last resolved segment, so this field was looked up on
                    // App\Entity\Address instead of App\Entity\Supplier and no error was
                    // ever reported for it.
                    'The field "plainFieldAfterDottedField" needs to exists as property in class "App\Entity\Supplier".',
                    43,
                ],
                [
                    // Third segment of a recursive field. The first two segments
                    // ("address", "country") resolve to App\Entity\Country, so the
                    // missing third segment is looked up on — and reported
                    // against — that class, not the grid's root resource class.
                    'The field "missingThirdSegment" needs to exists as property in class "App\Entity\Country".',
                    61,
                ],
                [
                    // CallableField::createForService() was skipped by the collector,
                    // which only accepted a static call named create().
                    'The field "missingCallableServiceField" needs to exists as property in class "App\Entity\Supplier".',
                    67,
                ],
            ],
        );
    }

    public function testRecursiveWithNullable(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/grid_recursive.php'],
            [
                [
                    // The "unknown" segment is looked up on App\Entity\Patient (the
                    // type the "patient" segment resolved to), so the error names
                    // that class, not the grid's root resource class.
                    'The field "unknown" needs to exists as property in class "App\Entity\Patient".',
                    25,
                ],
                [
                    'Unable to identify the type of the field "untyped" in class "App\Entity\Medipack".',
                    28,
                ],
                [
                    // The "missingRoot" segment is looked up directly on the grid's
                    // root resource class, so the error still names it.
                    'The field "missingRoot" needs to exists as property in class "App\Entity\Medipack".',
                    31,
                ],
            ],
        );
    }
}
