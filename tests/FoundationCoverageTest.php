<?php

use Magdicom\Hooks;

test('priority ordering is applied consistently across action filter and collector hooks', function () {
    $hooks = new Hooks();
    $actionEvents = [];

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

    $hooks->doAction('PriorityAction');

    expect($actionEvents)->toBe(['first', 'second', 'third'])
        ->and($hooks->applyFilters('PriorityFilter', 'start'))->toBe('start-first-second-third')
        ->and($hooks->collect('PriorityCollector'))->toBe(['first', 'second', 'third']);
});

test('repeated invocations of the new apis remain independent', function () {
    $hooks = new Hooks();
    $actionEvents = [];

    $hooks->addAction('RepeatAction', function (string $value) use (&$actionEvents): void {
        $actionEvents[] = $value;
    });

    $hooks->addFilter('RepeatFilter', fn (string $value, string $suffix): string => $value . '-' . $suffix);
    $hooks->addCollector('RepeatCollector', fn (string $value): array => ['value' => $value]);

    $hooks->doAction('RepeatAction', 'first');
    $hooks->doAction('RepeatAction', 'second');

    expect($actionEvents)->toBe(['first', 'second'])
        ->and($hooks->applyFilters('RepeatFilter', 'start', 'one'))->toBe('start-one')
        ->and($hooks->applyFilters('RepeatFilter', 'start', 'two'))->toBe('start-two')
        ->and($hooks->collect('RepeatCollector', 'alpha'))->toBe([['value' => 'alpha']])
        ->and($hooks->collect('RepeatCollector', 'beta'))->toBe([['value' => 'beta']]);
});
