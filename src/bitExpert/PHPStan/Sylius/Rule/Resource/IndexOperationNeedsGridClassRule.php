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

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * @implements Rule<InClassNode>
 */
class IndexOperationNeedsGridClassRule implements Rule
{
    public function __construct(private ReflectionProvider $broker)
    {
    }

    /**
     * @return class-string<InClassNode>
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassNode) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ((null === $classReflection) || (!$classReflection->implementsInterface('Sylius\Resource\Model\ResourceInterface'))) {
            return [];
        }

        $resourceClassAttributes = $classReflection->getAttributes();
        foreach ($resourceClassAttributes as $attribute) {
            if ('Sylius\Resource\Metadata\Index' === $attribute->getName()) {
                $argumentTypes = $attribute->getArgumentTypes();
                $gridClass = self::resolveConstantString($argumentTypes['grid'] ?? null);
                if (null === $gridClass) {
                    continue;
                }

                try {
                    $this->broker->getClass($gridClass);
                } catch (\Throwable) {
                    $message = \sprintf('Grid class "%s" not found!', $gridClass);

                    return [
                        RuleErrorBuilder::message($message)
                            ->identifier('sylius.resource.gridClassNotFound')
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }

    /**
     * Attribute arguments are not guaranteed to be a single constant string, for
     * example #[Index(grid: new SomeType())] yields an ObjectType. Calling
     * Type::getValue() on such an argument raises an internal error and aborts the
     * whole analysis, so only single constant strings are accepted.
     */
    private static function resolveConstantString(?Type $argumentType): ?string
    {
        if (null === $argumentType) {
            return null;
        }

        $constantStrings = $argumentType->getConstantStrings();

        return 1 === \count($constantStrings) ? $constantStrings[0]->getValue() : null;
    }
}
