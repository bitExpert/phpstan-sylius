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

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectFieldsForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * @implements Rule<CollectedDataNode>
 */
readonly class GridBuilderFieldIsPartOfResourceClass implements Rule
{
    public function __construct(protected ReflectionProvider $broker)
    {
    }

    /**
     * @return class-string<CollectedDataNode>
     */
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $gridResourceMap = [];
        $gridFilesMap = [];
        $gridFieldsMap = [];

        $resources = $node->get(CollectRessourceClassForGridClass::class);
        /** @var array<string, array<string, string>> $resources */
        foreach ($resources as $mapping) {
            foreach ($mapping as $mappingConfig) {
                $gridClassName = $mappingConfig[0] ?? null;
                $resourceClassName = $mappingConfig[1] ?? null;

                if (null !== $gridClassName && null !== $resourceClassName) {
                    $gridResourceMap[$gridClassName] = $resourceClassName;
                }
            }
        }

        $fields = $node->get(CollectFieldsForGridClass::class);
        /** @var array<string, array<string, array<string, string>>> $fields */
        foreach ($fields as $file => $mapping) {
            foreach ($mapping as $mappingConfig) {
                $gridClassName = $mappingConfig[0] ?? null;
                $resourceField = $mappingConfig[1] ?? null;
                $lineNo = $mappingConfig[2] ?? null;

                if (null !== $gridClassName && null !== $resourceField && null !== $lineNo) {
                    $gridFieldsMap[$gridClassName][] = [$resourceField, $lineNo];
                    $gridFilesMap[$gridClassName] = $file;
                }
            }
        }

        $errors = [];
        /** @var array<string, string> $gridResourceMap */
        foreach ($gridResourceMap as $gridClassName => $resourceClassName) {
            if (isset($gridFieldsMap[$gridClassName])) {
                foreach ($gridFieldsMap[$gridClassName] as $field) {
                    $fieldName = $field[0];
                    $lineNo = $field[1];

                    // The resource class has to be re-resolved for every field. The
                    // recursive branch below walks down the field path and replaces
                    // this with the type of the last resolved segment, which would
                    // otherwise leak into the next field of the same grid.
                    $resourceClass = $this->broker->getClass($resourceClassName);

                    if (!\str_contains($fieldName, '.')) {
                        // single property check
                        $getterMethod = 'get' . \ucfirst($fieldName);
                        if (!$resourceClass->hasProperty($fieldName) && !$resourceClass->hasMethod($getterMethod)) {
                            $message = \sprintf(
                                'The field "%s" needs to exists as property in class "%s".',
                                $fieldName,
                                $resourceClassName,
                            );

                            $errors[] = RuleErrorBuilder::message($message)
                                ->identifier('sylius.grid.resourceClassMissingProperty')
                                ->file($gridFilesMap[$gridClassName])
                                ->line($lineNo)
                                ->build();
                        }

                        continue;
                    }

                    // recursive property check
                    $fieldNames = \explode('.', $fieldName);
                    while (\count($fieldNames) > 0) {
                        $segment = \array_shift($fieldNames);
                        $getterMethod = 'get' . \ucfirst($segment);

                        if (!$resourceClass->hasProperty($segment) && !$resourceClass->hasMethod($getterMethod)) {
                            $message = \sprintf(
                                'The field "%s" needs to exists as property in class "%s".',
                                $segment,
                                $resourceClass->getName(),
                            );

                            $errors[] = RuleErrorBuilder::message($message)
                                ->identifier('sylius.grid.resourceClassMissingProperty')
                                ->file($gridFilesMap[$gridClassName])
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
                                    ->identifier('sylius.grid.resourceClassPropertyMissingType')
                                    ->file($gridFilesMap[$gridClassName])
                                    ->line($lineNo)
                                    ->build();
                            }

                            break;
                        }

                        $resourceClass = $nextClass;
                    }
                }
            }
        }

        return $errors;
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
