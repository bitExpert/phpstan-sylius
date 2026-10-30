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

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectGridNameForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\GridMutator\CollectGridNames;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * Shared skeleton for the rules that validate the fields/filters added to a
 * grid builder against the resource class of the grid, no matter whether the
 * builder is used inside a grid class or inside a grid mutator.
 *
 * @implements Rule<CollectedDataNode>
 */
abstract readonly class AbstractGridBuilderResourceRule implements Rule
{
    /**
     * @param class-string<Collector<Node, mixed>> $itemsCollector collector
     *                                                             that yields the field/filter tuples, keyed by the class owning the
     *                                                             builder (grid class or grid mutator class)
     */
    public function __construct(
        protected ReflectionProvider $broker,
        private string $itemsCollector,
    ) {
    }

    /**
     * @return class-string<CollectedDataNode>
     */
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $ownerResourceMap = $this->resolveResourceMap($node);

        /** @var array<string, list<array{0: string|string[], 1: int}>> $ownerItemsMap */
        $ownerItemsMap = [];
        /** @var array<string, string> $ownerFilesMap */
        $ownerFilesMap = [];

        $items = $node->get($this->itemsCollector);
        /** @var array<string, list<array{0: string, 1: string|string[], 2: int}>> $items */
        foreach ($items as $file => $mapping) {
            foreach ($mapping as $mappingConfig) {
                $ownerClassName = $mappingConfig[0] ?? null;
                $item = $mappingConfig[1] ?? null;
                $lineNo = $mappingConfig[2] ?? null;

                if (null !== $ownerClassName && null !== $item && null !== $lineNo) {
                    $ownerItemsMap[$ownerClassName][] = [$item, $lineNo];
                    $ownerFilesMap[$ownerClassName] = $file;
                }
            }
        }

        /** @var list<IdentifierRuleError> $errors */
        $errors = [];
        /** @var array<string, string|list<string>> $ownerResourceMap */
        foreach ($ownerResourceMap as $ownerClassName => $resourceClassNames) {
            if (!isset($ownerItemsMap[$ownerClassName])) {
                continue;
            }

            $resourceClassNames = \is_array($resourceClassNames) ? $resourceClassNames : [$resourceClassNames];
            foreach ($ownerItemsMap[$ownerClassName] as $item) {
                foreach ($resourceClassNames as $resourceClassName) {
                    foreach ($this->validateItem(
                        $ownerClassName,
                        $resourceClassName,
                        $item[0],
                        $item[1],
                        $ownerFilesMap[$ownerClassName],
                        $scope,
                    ) as $error) {
                        $errors[] = $error;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Maps the class owning the grid builder (grid class or grid mutator
     * class) to the resource class the fields/filters have to exist on.
     *
     * A single resource class name or a list of them, for mutators attached
     * to several grids (repeatable AsGridMutator).
     *
     * @return array<string, string|list<string>>
     */
    abstract protected function resolveResourceMap(CollectedDataNode $node): array;

    /**
     * Maps every grid class to its resource class.
     *
     * @return array<string, string>
     */
    protected function resolveGridResourceMap(CollectedDataNode $node): array
    {
        $gridResourceMap = [];

        $resources = $node->get(CollectRessourceClassForGridClass::class);
        foreach ($resources as $mapping) {
            foreach ($mapping as $mappingConfig) {
                $gridClassName = $mappingConfig[0] ?? null;
                $resourceClassName = $mappingConfig[1] ?? null;

                if (null !== $gridClassName && null !== $resourceClassName) {
                    $gridResourceMap[$gridClassName] = $resourceClassName;
                }
            }
        }

        return $gridResourceMap;
    }

    /**
     * Maps every grid mutator class to the resource class of the grid it
     * mutates: mutator class -> grid name -> grid class -> resource class.
     * Grid names are treated as unique (in practice the grid class name is
     * used as the grid name).
     *
     * The resource class of a grid is resolved from the collected data of
     * both CollectRessourceClassForGridClass (legacy getResourceClass()) and
     * CollectGridNameForGridClass (AsGrid attribute), because a 1.16 grid
     * may declare nothing but the attribute.
     *
     * A mutator attached to several grids (repeatable AsGridMutator) maps to
     * the list of all those grids' resource classes.
     *
     * @return array<string, list<string>>
     */
    protected function resolveMutatorResourceMap(CollectedDataNode $node): array
    {
        $gridResourceMap = $this->resolveGridResourceMap($node);

        $gridNameResourceMap = [];
        $gridNames = $node->get(CollectGridNameForGridClass::class);
        foreach ($gridNames as $mapping) {
            foreach ($mapping as $mappingConfig) {
                $gridClassName = $mappingConfig[0] ?? null;
                $gridName = $mappingConfig[1] ?? null;
                $resourceClassName = $mappingConfig[2] ?? null;

                if (null !== $resourceClassName) {
                    $gridResourceMap[$gridClassName] = $resourceClassName;
                }

                if (null !== $gridClassName && null !== $gridName && isset($gridResourceMap[$gridClassName])) {
                    $gridNameResourceMap[$gridName] = $gridResourceMap[$gridClassName];
                }
            }
        }

        $mutatorResourceMap = [];
        $mutators = $node->get(CollectGridNames::class);
        /** @var array<string, list<array{string, non-empty-list<string>, int}>> $mutators */
        foreach ($mutators as $mapping) {
            foreach ($mapping as $mappingConfig) {
                $mutatorClassName = $mappingConfig[0] ?? null;
                $gridNames = $mappingConfig[1] ?? null;

                if (null === $mutatorClassName || null === $gridNames) {
                    continue;
                }

                // a repeatable AsGridMutator attaches a mutator to several
                // grids; the fields are validated against every grid's
                // resource class, mirroring the runtime behaviour
                foreach ($gridNames as $gridName) {
                    if (isset($gridNameResourceMap[$gridName])) {
                        $mutatorResourceMap[$mutatorClassName][] = $gridNameResourceMap[$gridName];
                    }
                }
            }
        }

        return $mutatorResourceMap;
    }

    /**
     * Validates a single collected field/filter tuple against the resource
     * class and returns the errors found.
     *
     * @param string|string[] $item either the field name (fields) or a list of
     *                              filter field names (filters)
     *
     * @return list<IdentifierRuleError>
     */
    abstract protected function validateItem(
        string $ownerClassName,
        string $resourceClassName,
        mixed $item,
        int $lineNo,
        string $file,
        Scope $scope,
    ): array;

    /**
     * Validates a single field name, including recursive dotted paths
     * (e.g. "address.city").
     *
     * @return list<IdentifierRuleError>
     */
    protected function validateFieldItem(
        string $ownerClassName,
        string $resourceClassName,
        string $fieldName,
        int $lineNo,
        string $file,
        Scope $scope,
        string $missingPropertyIdentifier,
        string $missingTypeIdentifier,
    ): array {
        /** @var list<IdentifierRuleError> $errors */
        $errors = [];

        // The resource class has to be re-resolved for every field. The
        // recursive branch below walks down the field path and replaces
        // this with the type of the last resolved segment, which would
        // otherwise leak into the next field of the same builder.
        $resourceClass = $this->broker->getClass($resourceClassName);

        if (!\str_contains($fieldName, '.')) {
            // single property check
            $getterMethod = 'get' . \ucfirst($fieldName);
            if (!$resourceClass->hasProperty($fieldName) && !$resourceClass->hasMethod($getterMethod)) {
                $errors[] = RuleErrorBuilder::message($this->missingPropertyMessage($ownerClassName, $fieldName, $resourceClassName))
                    ->identifier($missingPropertyIdentifier)
                    ->file($file)
                    ->line($lineNo)
                    ->build();
            }

            return $errors;
        }

        // recursive property check
        $fieldNames = \explode('.', $fieldName);
        while (\count($fieldNames) > 0) {
            $segment = \array_shift($fieldNames);
            $getterMethod = 'get' . \ucfirst($segment);

            if (!$resourceClass->hasProperty($segment) && !$resourceClass->hasMethod($getterMethod)) {
                $errors[] = RuleErrorBuilder::message($this->missingPropertyMessage($ownerClassName, $segment, $resourceClass->getName()))
                    ->identifier($missingPropertyIdentifier)
                    ->file($file)
                    ->line($lineNo)
                    ->build();

                break;
            }

            $nextClass = $this->resolveNextClass($resourceClass, $segment, $getterMethod, $scope);
            if (null === $nextClass) {
                if (\count($fieldNames) > 0) {
                    $message = \sprintf(
                        'Unable to identify the type of the field "%s" in class "%s".',
                        $segment,
                        $resourceClass->getName(),
                    );

                    $errors[] = RuleErrorBuilder::message($message)
                        ->identifier($missingTypeIdentifier)
                        ->file($file)
                        ->line($lineNo)
                        ->build();
                }

                break;
            }

            $resourceClass = $nextClass;
        }

        return $errors;
    }

    /**
     * Validates a list of filter field names. Filters do not support recursion
     * via ".", so dotted field names are skipped.
     *
     * @param string[] $fieldNames
     *
     * @return list<IdentifierRuleError>
     */
    protected function validateFilterItem(
        string $ownerClassName,
        string $resourceClassName,
        array $fieldNames,
        int $lineNo,
        string $file,
        string $missingFilterIdentifier,
    ): array {
        /** @var list<IdentifierRuleError> $errors */
        $errors = [];

        $resourceClass = $this->broker->getClass($resourceClassName);

        foreach ($fieldNames as $fieldName) {
            // filters do not support recursion via .
            if (\str_contains($fieldName, '.')) {
                continue;
            }

            $getterMethod = 'get' . \ucfirst($fieldName);
            if (!$resourceClass->hasProperty($fieldName) && !$resourceClass->hasMethod($getterMethod)) {
                $errors[] = RuleErrorBuilder::message($this->missingFilterMessage($ownerClassName, $fieldName, $resourceClassName))
                    ->identifier($missingFilterIdentifier)
                    ->file($file)
                    ->line($lineNo)
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * Message reported when a field/property is missing on the resource class.
     */
    protected function missingPropertyMessage(string $ownerClassName, string $fieldName, string $resourceClassName): string
    {
        return \sprintf(
            'The field "%s" needs to exists as property in class "%s".',
            $fieldName,
            $resourceClassName,
        );
    }

    /**
     * Message reported when a filter field is missing on the resource class.
     */
    protected function missingFilterMessage(string $ownerClassName, string $fieldName, string $resourceClassName): string
    {
        return \sprintf(
            'The filter field "%s" needs to exists as property in resource class "%s".',
            $fieldName,
            $resourceClassName,
        );
    }

    /**
     * Resolves the type a single field path segment points at, as a ClassReflection
     * so the next segment can be looked up on it. Returns null when the type cannot
     * be narrowed down to exactly one class.
     */
    private function resolveNextClass(
        ClassReflection $class,
        string $segment,
        string $getterMethod,
        Scope $scope,
    ): ?ClassReflection {
        $candidates = [];

        try {
            $candidates[] = $class->getMethod($getterMethod, $scope)->getOnlyVariant()->getReturnType();
        } catch (\Exception) {
        }

        try {
            $property = $class->getProperty($segment, $scope);
            if ($property->hasNativeType()) {
                $candidates[] = $property->getNativeType();
            } elseif ($property->hasPHPDocType()) {
                $candidates[] = $property->getPhpDocType();
            }
        } catch (MissingPropertyFromReflectionException) {
        }

        foreach ($candidates as $candidate) {
            $candidate = TypeCombinator::removeNull($candidate);
            if (!$candidate->isObject()->yes()) {
                continue;
            }
            $classNames = $candidate->getObjectClassNames();
            if (1 === \count($classNames)) {
                try {
                    return $this->broker->getClass($classNames[0]);
                } catch (\Throwable) {
                }
            }
        }

        return null;
    }
}
