<?php

use Magdicom\Hooks;

test('legacy chaining remains usable through the compatibility layer', function () {
    $hooks = new Hooks();

    expect(
        $hooks->register('LegacyChain', fn (): string => 'Hello', 10)
            ->register('LegacyChain', fn (): string => 'World', 20)
            ->all('LegacyChain')
            ->toString(' ')
    )->toBe('Hello World');
});

test('legacy dispatch stays isolated from action filter and collector hooks with the same name', function () {
    $hooks = new Hooks();
    $actions = [];

    $hooks->register('SharedName', fn (): string => 'legacy', 10);
    $hooks->addAction('SharedName', function () use (&$actions): void {
        $actions[] = 'action';
    }, 20);
    $hooks->addFilter('SharedName', fn (string $value): string => strtoupper($value), 30);
    $hooks->addCollector('SharedName', fn (): string => 'collector', 40);

    expect($hooks->all('SharedName')->toString())->toBe('legacy')
        ->and($hooks->doAction('SharedName'))->toBe($hooks)
        ->and($actions)->toBe(['action'])
        ->and($hooks->applyFilters('SharedName', 'filter'))->toBe('FILTER')
        ->and($hooks->collect('SharedName'))->toBe(['collector']);
});

test('legacy output helpers reflect only the last legacy invocation', function () {
    $hooks = new Hooks();

    $hooks->register('LegacyOutput', fn (): string => 'legacy');
    $hooks->addCollector('LegacyOutput', fn (): string => 'collector');

    expect($hooks->all('LegacyOutput')->toString())->toBe('legacy')
        ->and($hooks->collect('LegacyOutput'))->toBe(['collector'])
        ->and($hooks->toArray())->toBe([])
        ->and($hooks->all('LegacyOutput')->toArray())->toBe(['legacy']);
});
