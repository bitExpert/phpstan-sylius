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
use PHPStan\Node\InClassMethodNode;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionClass;
use ReflectionMethod;
use Sylius\Resource\Metadata\AsResource;

class ResourceAwareGridNeedsResourceClass implements Rule
{
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassMethodNode) {
            return [];
        }

        $methodReflection = $node->getMethodReflection();
        if ($methodReflection->getName() !== 'getResourceClass') {
            return [];
        }

        // grab the return value of getResourceClass() method to identify the assigned resource class
        $className = $node->getClassReflection()->getName();
        $reflectionMethod = new ReflectionMethod($className, 'getResourceClass');
        $resourceClass = $reflectionMethod->invoke(new $className);

        // check if the class is marked with #[AsResource] attribute
        $reflectionClass = new ReflectionClass($resourceClass);
        $attributes = $reflectionClass->getAttributes(AsResource::class);
        if (count($attributes) > 0) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf('getResourceClass() needs to provide a resource class. Mark "%s" with #[AsResource] attribute.', $resourceClass))
                ->identifier('sylius.grid.resourceClassRequired')
                ->build(),
        ];
    }
}
