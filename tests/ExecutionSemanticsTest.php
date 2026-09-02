<?php

use Magdicom\Hooks;
use Magdicom\RegistrationHandle;

test('actions execute in order and ignore callback return values', function () {
    $hooks = new Hooks();
    $events = [];

    $hooks->addAction('ActionHook', function (string $name) use (&$events) {
        $events[] = 'first:' . $name;

        return 'ignored';
    }, 10);

    $hooks->addAction('ActionHook', function (string $name) use (&$events) {
        $events[] = 'second:' . $name;

        return ['ignored' => true];
    }, 20);

    $hooks->doAction('ActionHook', 'hooks');

    expect($events)->toBe(['first:hooks', 'second:hooks']);
});

test('filters apply sequential transformations', function () {
    $hooks = new Hooks();

    $hooks->addFilter('Title', fn (string $value, string $suffix): string => trim($value) . $suffix, 10);
    $hooks->addFilter('Title', fn (string $value): string => strtoupper($value), 20);

    expect($hooks->applyFilters('Title', ' hello ', '!'))->toBe('HELLO!');
});

test('filters support typed context objects through variadic arguments', function () {
    $hooks = new Hooks();

    $hooks->addFilter('ObjectFilter', function (string $value, FilterContext $context, string $suffix): string {
        return $value . ':' . $context->name . $suffix;
    });

    expect($hooks->applyFilters('ObjectFilter', 'hello', new FilterContext('world'), '!'))->toBe('hello:world!');
});

test('collectors return raw callback results without flattening', function () {
    $hooks = new Hooks();

    $hooks->addCollector('Collect', fn (): array => ['id' => 1], 10);
    $hooks->addCollector('Collect', fn (): array => [['id' => 2]], 20);
    $hooks->addCollector('Collect', fn (): string => 'done', 30);

    expect($hooks->collect('Collect'))->toBe([
        ['id' => 1],
        [['id' => 2]],
        'done',
    ]);
});

test('inspection and removal APIs include action filter and collector registrations', function () {
    $hooks = new Hooks();
    $filterCallback = fn (string $value): string => $value . ' filtered';

    $action = $hooks->addAction('Shared', fn (): null => null, 5);
    $filter = $hooks->addFilter('Shared', $filterCallback, 10);
    $collector = $hooks->addCollector('Shared', fn (): string => 'collected', 15);

    $listeners = $hooks->listeners('Shared');

    expect($hooks->count('Shared'))->toBe(3)
        ->and($hooks->has('Shared'))->toBeTrue()
        ->and($hooks->hasAction('Shared', $action))->toBeTrue()
        ->and($hooks->hasFilter('Shared', $filterCallback))->toBeTrue()
        ->and($hooks->hasCollector('Shared', $collector))->toBeTrue()
        ->and(array_map(fn (RegistrationHandle $handle): int => $handle->id(), $hooks->actions('Shared')))
        ->toBe([$action->id()])
        ->and(array_map(fn (RegistrationHandle $handle): int => $handle->id(), $hooks->filters('Shared')))
        ->toBe([$filter->id()])
        ->and(array_map(fn (RegistrationHandle $handle): int => $handle->id(), $hooks->collectors('Shared')))
        ->toBe([$collector->id()])
        ->and(array_map(fn (RegistrationHandle $handle): string => $handle->type(), $listeners))
        ->toBe(['action', 'filter', 'collector'])
        ->and($hooks->removeCollector('Shared', $collector))->toBeTrue()
        ->and($hooks->count('Shared'))->toBe(2);
});

class FilterContext
{
    public function __construct(public string $name)
    {
    }
}
