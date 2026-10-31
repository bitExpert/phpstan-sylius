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

namespace bitExpert\PHPStan\Sylius\Rule\Resource;

use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;

/**
 * Base class for every rule that inspects the attributes of a Sylius resource
 * class. Provides the InClassNode handling and the ResourceInterface scope
 * check all resource rules share.
 *
 * @implements Rule<InClassNode>
 */
abstract class AbstractResourceRule implements Rule
{
    protected const RESOURCE_INTERFACE = 'Sylius\Resource\Model\ResourceInterface';

    public function __construct(protected ReflectionProvider $broker)
    {
    }

    /**
     * @return class-string<InClassNode>
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * Returns the class reflection of the analysed node when it is a Sylius
     * resource, null otherwise: every resource rule only fires on classes
     * implementing ResourceInterface.
     */
    protected function getResourceClassReflection(Scope $scope): ?ClassReflection
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection || !$classReflection->implementsInterface(self::RESOURCE_INTERFACE)) {
            return null;
        }

        return $classReflection;
    }
}
