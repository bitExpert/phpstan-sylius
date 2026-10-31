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

use bitExpert\PHPStan\Util\ConstantString;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Base class for the rules that validate a single constant-string argument
 * of an attribute on a resource class: the referenced class has to exist.
 *
 * The attribute is read through AttributeReflection, so only attributes
 * declared directly on the resource class are seen — use
 * AbstractResourceOperationRule when the operations nested in
 * AsResource(operations: [...]) have to be validated too.
 */
abstract class AbstractResourceAttributeClassRule extends AbstractResourceRule
{
    /**
     * Fully qualified name of the attribute the rule validates.
     */
    abstract protected function getAttributeName(): string;

    /**
     * Name of the attribute argument holding the referenced class name.
     */
    abstract protected function getArgumentName(): string;

    /**
     * Message reported when the referenced class does not exist, with a
     * "%s" placeholder for the class name.
     */
    abstract protected function getNotFoundMessageTemplate(): string;

    /**
     * Error identifier of the reported error.
     */
    abstract protected function getNotFoundIdentifier(): string;

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof InClassNode) {
            return [];
        }

        $classReflection = $this->getResourceClassReflection($scope);
        if (null === $classReflection) {
            return [];
        }

        $errors = [];
        foreach ($classReflection->getAttributes() as $attribute) {
            if ($this->getAttributeName() !== $attribute->getName()) {
                continue;
            }

            $argumentTypes = $attribute->getArgumentTypes();
            $referencedClass = ConstantString::fromType($argumentTypes[$this->getArgumentName()] ?? null);
            if (null === $referencedClass) {
                continue;
            }

            $error = $this->validateClassExists($referencedClass);
            if (null !== $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    protected function validateClassExists(string $className): ?IdentifierRuleError
    {
        try {
            $this->broker->getClass($className);
        } catch (\Throwable) {
            return RuleErrorBuilder::message(\sprintf($this->getNotFoundMessageTemplate(), $className))
                ->identifier($this->getNotFoundIdentifier())
                ->build();
        }

        return null;
    }
}
