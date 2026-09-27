<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'vendor',
        // Analysis fixtures are loaded through composer autoload-dev.files, not
        // by class name, so the psr_autoloading rule that @Symfony enables does
        // not apply to them: their file name is snake_case while their classes
        // are PSR-1. Without this the fixer would rewrite every fixture class to
        // its file's basename, which breaks the references between fixtures.
        'tests/bitExpert/PHPStan/Sylius/Rule/Grid/data',
        'tests/bitExpert/PHPStan/Sylius/Rule/Resource/data',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'ordered_imports' => true,
        'concat_space' => ['spacing' => 'one'],
        'array_syntax' => ['syntax' => 'short'],
        'phpdoc_align' => ['align' => 'left'],
        'class_definition' => [
            'multi_line_extends_each_single_line' => true,
        ] ,
        'linebreak_after_opening_tag' => true,
        'declare_strict_types' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'native_constant_invocation' => true,
        'native_function_casing' => true,
        'native_function_invocation' => ['include' => ['@internal']],
        'no_php4_constructor' => true,
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => true],
        'no_unreachable_default_argument_value' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'php_unit_strict' => true,
        'phpdoc_order' => true,
        'semicolon_after_instruction' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'array_indentation' => true,
        'multiline_whitespace_before_semicolons' => true,
        'single_line_throw' => false,
        'visibility_required' => ['elements' => ['property', 'method', 'const']],
        'phpdoc_to_comment' => [
            'ignored_tags' => ['todo', 'var'],
        ],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
    ])
    ->setFinder($finder);
