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
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\Type;

/**
 * Collects the field factory calls of a grid builder, no matter whether the
 * builder is used inside a grid class or inside a grid mutator.
 *
 * @implements Collector<StaticCall, array{string, string, int}>
 */
abstract class AbstractGridBuilderFieldCollector extends AbstractGridBuilderCallCollector implements Collector
{
    /**
     * Grid fields are built through a static factory. Every field class uses create(),
     * and CallableField additionally offers createForService() for a service-backed
     * callable. Both take the field name as their first argument.
     */
    private const FACTORY_METHODS = ['create', 'createForService'];

    public function __construct(private readonly FieldRegistry $fieldRegistry)
    {
    }

    /**
     * Whether the current scope belongs to a grid or to a grid mutator.
     */
    abstract protected function scopeIsBuilderScope(Scope $scope): bool;

    protected function isBuilderCall(StaticCall $node, Scope $scope): bool
    {
        if (!$node->name instanceof Identifier || !\in_array($node->name->toString(), self::FACTORY_METHODS, true)) {
            return false;
        }

        if (!$this->scopeIsBuilderScope($scope)) {
            return false;
        }

        return $this->isFieldInterfaceReturnType($scope->getType($node));
    }

    /**
     * @return array{string, string, int}|null
     */
    protected function collect(StaticCall $node, string $className): ?array
    {
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

                return [$className, $fieldNames[0], $node->getLine()];
            }
        }

        return null;
    }

    protected function isFieldInterfaceReturnType(Type $type): bool
    {
        // new Grid Bundle logic since v1.16.0
        if ($this->isSubtypeOf($type, '\Sylius\Component\Grid\Builder\Field\FieldInterface')) {
            return true;
        }

        // old Grid Bundle logic
        return $this->isSubtypeOf($type, '\Sylius\Bundle\GridBundle\Builder\Field\FieldInterface');
    }
}
