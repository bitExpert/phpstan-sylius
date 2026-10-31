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

/**
 * Validates the form type referenced by #[AsResource(formType: '...')] on a
 * resource class: the referenced class has to exist.
 */
class ResourceAttributeNeedsFormTypeRule extends AbstractResourceAttributeClassRule
{
    protected function getAttributeName(): string
    {
        return 'Sylius\Resource\Metadata\AsResource';
    }

    protected function getArgumentName(): string
    {
        return 'formType';
    }

    protected function getNotFoundMessageTemplate(): string
    {
        return 'Form Type "%s" not found!';
    }

    protected function getNotFoundIdentifier(): string
    {
        return 'sylius.resource.formTypeNotFound';
    }
}
