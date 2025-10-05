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

use bitExpert\PHPStan\Sylius\Collector\Grid\CollectFilterForGridClass;
use bitExpert\PHPStan\Sylius\Collector\Grid\CollectRessourceClassForGridClass;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<StaticCall>
 */
readonly class GridBuilderFilterIsPartOfResourceClass implements Rule
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
        $gridFilterFieldsMap = [];

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

        $filters = $node->get(CollectFilterForGridClass::class);
        /** @var array<string, array<string, array<string, string>>> $filters */
        foreach ($filters as $file => $mapping) {
            foreach ($mapping as $mappingConfig) {
                $gridClassName = $mappingConfig[0] ?? null;
                $resourceField = $mappingConfig[1] ?? null;

                $lineNo = $mappingConfig[2] ?? null;

                if (null !== $gridClassName && null !== $resourceField && null !== $lineNo) {
                    $gridFilterFieldsMap[$gridClassName][] = [$resourceField, $lineNo];
                    $gridFilesMap[$gridClassName] = $file;
                }
            }
        }

        $errors = [];
        /** @var array<string, string> $gridResourceMap */
        foreach ($gridResourceMap as $gridClassName => $resourceClassName) {
            if (isset($gridFilterFieldsMap[$gridClassName])) {
                $resourceClass = $this->broker->getClass($resourceClassName);

                foreach ($gridFilterFieldsMap[$gridClassName] as $field) {
                    $fieldName = $field[0];
                    $lineNo = $field[1];

                    $getterMethod = 'get' . \ucfirst($fieldName);
                    if (!$resourceClass->hasProperty($fieldName) && !$resourceClass->hasMethod($getterMethod)) {
                        $message = \sprintf(
                            'The filter field "%s" needs to exists as property in resource class "%s".',
                            $fieldName,
                            $resourceClassName,
                        );

                        $errors[] = RuleErrorBuilder::message($message)
                            ->identifier('sylius.grid.resourceClassMissingFilter')
                            ->file($gridFilesMap[$gridClassName])
                            ->line($lineNo)
                            ->build();
                    }
                }
            }
        }

        return $errors;
    }
}
