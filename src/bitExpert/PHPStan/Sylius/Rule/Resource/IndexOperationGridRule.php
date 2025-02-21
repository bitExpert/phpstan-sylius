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
use PHPStan\Type\Constant\ConstantStringType;

class IndexOperationGridRule implements Rule
{
    public function __construct(private ReflectionProvider $broker)
    {
    }

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
        if (!$classReflection->implementsInterface('Sylius\Resource\Model\ResourceInterface'))
        {
            return [];
        }

        $resourceClassAttributes = $classReflection->getAttributes();
        foreach ($resourceClassAttributes as $attribute) {
            if ($attribute->getName() === 'Sylius\Resource\Metadata\Index') {
                $argumentTypes = $attribute->getArgumentTypes();
                if (isset($argumentTypes['grid'])) {
                    /** @var ConstantStringType $argumentTypes['grid'] */
                    $gridClass = $argumentTypes['grid']->getValue();

                    try {
                        $this->broker->getClass($gridClass);
                    } catch (\Throwable $e) {
                        $message = sprintf('Grid class "%s" not found!', $gridClass);

                        return [
                            RuleErrorBuilder::message($message)
                                ->identifier('sylius.resource.gridClassNotFound')
                                ->build(),
                        ];
                    }
                }
            }
        }

        return [];
    }
}
