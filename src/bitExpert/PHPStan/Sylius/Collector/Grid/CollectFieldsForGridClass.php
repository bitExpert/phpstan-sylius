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

namespace bitExpert\PHPStan\Sylius\Collector\Grid;

use bitExpert\PHPStan\Sylius\Collector\Grid\Field\FieldRegistry;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * @implements Collector<StaticCall, array{string, string, int}>
 */
final class CollectFieldsForGridClass extends AbstractGridClassCollector implements Collector
{
    public function __construct(private readonly FieldRegistry $fieldRegistry)
    {
    }

    /**
     * @return class-string
     */
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof StaticCall) {
            return null;
        }

        if ((!$node->name instanceof Identifier) || ('create' !== $node->name->toString())) {
            return null;
        }

        if (!$this->scopeIsGrid($scope)) {
            return null;
        }

        if (!$this->isFieldInterfaceReturnType($scope->getType($node))) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return null;
        }
        $classType = new ObjectType($classReflection->getName());

        /** @var FullyQualified $nodeClass */
        $nodeClass = $node->class;

        // first check if the various field implementations have defined custom fields to check
        foreach ($this->fieldRegistry->getFields() as $fieldNode) {
            if ($fieldNode->supports($nodeClass)) {
                $fieldNames = $fieldNode->getFieldNames($node);

                if (0 === \count($fieldNames)) {
                    return null;
                }

                // the . means the resource object is passed to the grid field. That means, we can ignore it
                if ('.' === $fieldNames[0]) {
                    return null;
                }

                return [$classType->getClassName(), $fieldNames[0], $node->getLine()];
            }
        }

        return null;
    }

    protected function isFieldInterfaceReturnType(Type $type): bool
    {
        return $this->isSubtypeOf($type, '\Sylius\Bundle\GridBundle\Builder\Field\FieldInterface');
    }
}
