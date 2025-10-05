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
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<StaticCall>
 */
readonly class GridBuilderFieldIsPartOfResourceClass implements Rule
{
    public function __construct(protected ReflectionProvider $broker)
    {
    }

    /**
     * @return class-string
     */
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof CollectedDataNode) {
            return [];
        }

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
                $resourceClass = $this->broker->getClass($resourceClassName);

                foreach ($gridFieldsMap[$gridClassName] as $field) {
                    $fieldName = $field[0];
                    $lineNo = $field[1];

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
                    } else {
                        // recursive property check
                        $fieldNames = \explode('.', $fieldName);
                        while (\count($fieldNames) > 0) {
                            $fieldName = \array_shift($fieldNames);
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

                            if ($resourceClass->hasProperty($fieldName)) {
                                $property = $resourceClass->getProperty($fieldName, $scope);
                                if ($property->hasNativeType()) {
                                    $resourceClass = $property->getNativeType();
                                } elseif ($property->hasPHPDocType()) {
                                    $resourceClass = $property->getPhpDocType();
                                } elseif (\count($fieldNames) > 0) {
                                    /** @var ClassReflection $resourceClass */
                                    $message = \sprintf(
                                        'Unable to identify the type of the field "%s" in class "%s".',
                                        $fieldName,
                                        $resourceClass->getName(),
                                    );

                                    $errors[] = RuleErrorBuilder::message($message)
                                        ->identifier('sylius.grid.resourceClassPropertyMissingType')
                                        ->file($gridFilesMap[$gridClassName])
                                        ->line($lineNo)
                                        ->build();
                                }
                            } elseif ($resourceClass->hasMethod($getterMethod)) {
                                $resourceClass = $resourceClass->getMethod($fieldName, $scope)->getReturnType();
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
