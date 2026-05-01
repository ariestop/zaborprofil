<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/migrations',
        __DIR__ . '/tools',
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
    ])
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
        NewMethodCallWithoutParenthesesRector::class,
    ])
;
