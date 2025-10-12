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

namespace bitExpert\PHPStan\Unit;

use bitExpert\PHPStan\Util\PropertyName;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PropertyNameUnitTest extends TestCase
{
    #[Test]
    public function convertSnakeToCamelCaseWithUnderscores(): void
    {
        $snakeCase = 'first_name';
        $expectedCamelCase = 'firstName';

        $result = PropertyName::convertSnakeToCamelCase($snakeCase);

        $this->assertSame($expectedCamelCase, $result);
    }

    #[Test]
    public function convertSnakeToCamelCaseWithMultipleUnderscores(): void
    {
        $snakeCase = 'user_profile_image';
        $expectedCamelCase = 'userProfileImage';

        $result = PropertyName::convertSnakeToCamelCase($snakeCase);

        $this->assertSame($expectedCamelCase, $result);
    }
}
