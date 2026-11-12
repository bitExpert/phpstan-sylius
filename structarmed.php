<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;
use Boundwize\StructArmed\Rule\Rules\Class_\MustBeFinalRule;

return Architecture::define()
    ->skip([
        __DIR__ . '/tests/bitExpert/PHPStan/Sylius/Rule/Resource/data',
        __DIR__ . '/tests/bitExpert/PHPStan/Sylius/Rule/Grid/data',
    ])
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY())

    ->layer('Util', 'src/bitExpert/PHPStan/Util/')
    ->layer('Field', 'src/bitExpert/PHPStan/Sylius/Collector/Grid/Field/')
    ->layer('Filter', 'src/bitExpert/PHPStan/Sylius/Collector/Grid/Filter/')
    ->layer('Collector',
        // All classes in here belong to Collector,
        'src/bitExpert/PHPStan/Sylius/Collector/',
        // except these directories, which belong to the specific layers defined above.
        [
            'src/bitExpert/PHPStan/Sylius/Collector/Grid/Field/',
            'src/bitExpert/PHPStan/Sylius/Collector/Grid/Filter/',
        ]
    )
    ->layer('GridRule', 'src/bitExpert/PHPStan/Sylius/Rule/Grid/')
    ->layer('ResourceRule', 'src/bitExpert/PHPStan/Sylius/Rule/Resource/')
    ->ruleset([
        'Util' => [],
        'Field' => ['Util'],
        'Filter' => ['Util'],
        'Collector' => ['Field', 'Filter', 'Util'],
        'GridRule' => ['Collector', 'Util'],
        'ResourceRule' => ['Util'],
    ])

    ->rule('source.must_be_final', new MustBeFinalRule(layer: 'Source'));
