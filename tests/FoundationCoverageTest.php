<?php

use Magdicom\Hooks;

test('priority ordering is applied consistently across legacy action filter and collector hooks', function () {
    $hooks = new Hooks();
    $actionEvents = [];

    $hooks->register('PriorityLegacy', fn (): string => 'second', 20)
        ->register('PriorityLegacy', fn (): string => 'first', 10)
        ->register('PriorityLegacy', fn (): string => 'third', 30);

    $hooks->addAction('PriorityAction', function () use (&$actionEvents): void {
        $actionEvents[] = 'second';
    }, 20);
    $hooks->addAction('PriorityAction', function () use (&$actionEvents): void {
        $actionEvents[] = 'first';
    }, 10);
    $hooks->addAction('PriorityAction', function () use (&$actionEvents): void {
        $actionEvents[] = 'third';
    }, 30);

    $hooks->addFilter('PriorityFilter', fn (string $value): string => $value . '-second', 20);
    $hooks->addFilter('PriorityFilter', fn (string $value): string => $value . '-first', 10);
    $hooks->addFilter('PriorityFilter', fn (string $value): string => $value . '-third', 30);

    $hooks->addCollector('PriorityCollector', fn (): string => 'second', 20);
    $hooks->addCollector('PriorityCollector', fn (): string => 'first', 10);
    $hooks->addCollector('PriorityCollector', fn (): string => 'third', 30);

    expect($hooks->all('PriorityLegacy')->toArray())->toBe(['first', 'second', 'third'])
        ->and($hooks->doAction('PriorityAction'))->toBe($hooks)
        ->and($actionEvents)->toBe(['first', 'second', 'third'])
        ->and($hooks->applyFilters('PriorityFilter', 'start'))->toBe('start-first-second-third')
        ->and($hooks->collect('PriorityCollector'))->toBe(['first', 'second', 'third']);
});

test('repeated invocations of the new apis remain independent', function () {
    $hooks = new Hooks();
    $actionEvents = [];

    $hooks->addAction('RepeatAction', function (array $vars) use (&$actionEvents): void {
        $actionEvents[] = $vars['value'];
    });

    $hooks->addFilter('RepeatFilter', fn (string $value, array $vars): string => $value . '-' . $vars['suffix']);
    $hooks->addCollector('RepeatCollector', fn (array $vars): array => ['value' => $vars['value']]);

    expect($hooks->doAction('RepeatAction', ['value' => 'first']))->toBe($hooks)
        ->and($hooks->doAction('RepeatAction', ['value' => 'second']))->toBe($hooks)
        ->and($actionEvents)->toBe(['first', 'second'])
        ->and($hooks->applyFilters('RepeatFilter', 'start', ['suffix' => 'one']))->toBe('start-one')
        ->and($hooks->applyFilters('RepeatFilter', 'start', ['suffix' => 'two']))->toBe('start-two')
        ->and($hooks->collect('RepeatCollector', ['value' => 'alpha']))->toBe([['value' => 'alpha']])
        ->and($hooks->collect('RepeatCollector', ['value' => 'beta']))->toBe([['value' => 'beta']])
        ->and($hooks->toArray())->toBe([]);
});
