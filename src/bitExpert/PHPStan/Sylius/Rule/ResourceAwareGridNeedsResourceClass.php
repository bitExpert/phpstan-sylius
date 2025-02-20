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

namespace bitExpert\PHPStan\Sylius\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;

class ResourceAwareGridNeedsResourceClass implements \PHPStan\Rules\Rule
{
    public function getNodeType(): string
    {
        return \PhpParser\Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        var_dump(get_class($node));
        return [];
    }
}
