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

namespace bitExpert\PHPStan\Sylius\Rule\Grid;

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectFieldsForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\CallableFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DateTimeFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DefaultFieldRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\EnumFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\GenericFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\StringFieldNode;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\TwigFieldNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<GridBuilderFieldIsPartOfResourceClass>
 */
class GridBuilderFieldIsPartOfResourceClassUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new GridBuilderFieldIsPartOfResourceClass($this->createReflectionProvider());
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
            new CollectFieldsForGridClass(new DefaultFieldRegistry($fields)),
        ];
    }

    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/data/grid.php'],
            [
                [
                    'The field "name" needs to exists as property in class "App\Entity\Supplier".',
                    29,
                ],
                [
                    // This field is declared after the recursive "address.city" field.
                    // Walking the recursive path replaced the resource class with the
                    // type of the last resolved segment, so this field was looked up on
                    // App\Entity\Address instead of App\Entity\Supplier and no error was
                    // ever reported for it.
                    'The field "plainFieldAfterDottedField" needs to exists as property in class "App\Entity\Supplier".',
                    38,
                ],
                [
                    // Third segment of a recursive field. Once the walk leaves the
                    // ClassReflection it queried a Type, and Type::hasProperty()
                    // answers with a TrinaryLogic whose cast to bool is always true,
                    // so every segment past the first silently passed.
                    'The field "missingThirdSegment" needs to exists as property in class "App\Entity\Supplier".',
                    56,
                ],
            ],
        );
    }
}
