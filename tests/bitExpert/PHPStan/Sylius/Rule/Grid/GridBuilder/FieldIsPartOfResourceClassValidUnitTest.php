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
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\CallableField;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DateTimeField;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\DefaultFieldRegistry;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\EnumField;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\GenericField;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\StringField;
use bitExpert\PHPStan\Sylius\Collector\Grid\Field\TwigField;
use bitExpert\PHPStan\Sylius\Collector\Grid\GridBuilder\CollectFields;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @extends RuleTestCase<FieldIsPartOfResourceClass>
 */
class FieldIsPartOfResourceClassValidUnitTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FieldIsPartOfResourceClass($this->createReflectionProvider());
    }

    protected function getCollectors(): array
    {
        $fields = [];
        $fields[] = new StringField();
        $fields[] = new DateTimeField();
        $fields[] = new TwigField();
        $fields[] = new EnumField();
        $fields[] = new CallableField();
        $fields[] = new GenericField();

        return [
            new CollectRessourceClassForGridClass(),
            new CollectFields(new DefaultFieldRegistry($fields)),
        ];
    }

    #[Test]
    public function ruleSucceedsWhenAllFieldsExist(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/grid_valid.php'],
            [],
        );
    }
}
