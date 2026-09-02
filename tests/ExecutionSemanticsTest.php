<?php

use Magdicom\Hooks;
use Magdicom\RegistrationHandle;

test('actions execute in order and ignore callback return values', function () {
    $hooks = new Hooks();
    $events = [];

    $hooks->addAction('ActionHook', function (array $vars) use (&$events) {
        $events[] = 'first:' . $vars['name'];

        return 'ignored';
    }, 10);

    $hooks->addAction('ActionHook', function (array $vars) use (&$events) {
        $events[] = 'second:' . $vars['name'];

        return ['ignored' => true];
    }, 20);

    expect($hooks->doAction('ActionHook', ['name' => 'hooks']))->toBe($hooks)
        ->and($events)->toBe(['first:hooks', 'second:hooks'])
        ->and($hooks->toArray())->toBe([]);
});

test('filters apply sequential transformations', function () {
    $hooks = new Hooks(['suffix' => '!']);

    $hooks->addFilter('Title', fn (string $value, array $vars): string => trim($value) . $vars['suffix'], 10);
    $hooks->addFilter('Title', fn (string $value): string => strtoupper($value), 20);

    expect($hooks->applyFilters('Title', ' hello '))->toBe('HELLO!');
});

test('filters support object scoped parameters with globals as third argument', function () {
    $hooks = new Hooks(['suffix' => '!']);

    $hooks->addFilter('ObjectFilter', function (string $value, FilterContext $context, array $globals): string {
        return $value . ':' . $context->name . $globals['suffix'];
    });

    expect($hooks->applyFilters('ObjectFilter', 'hello', new FilterContext('world')))->toBe('hello:world!');
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
    ])->and($hooks->toArray())->toBe([]);
});

test('inspection and removal APIs include action filter and collector registrations', function () {
    $hooks = new Hooks();
    $filterCallback = fn (string $value): string => $value . ' filtered';

    $action = $hooks->addAction('Shared', fn (): null => null, 5);
    $hooks->addFilter('Shared', $filterCallback, 10);
    $collector = $hooks->addCollector('Shared', fn (): string => 'collected', 15);

    $listeners = $hooks->listeners('Shared');

    expect($hooks->count('Shared'))->toBe(3)
        ->and($hooks->has('Shared', $action))->toBeTrue()
        ->and($hooks->has('Shared', $filterCallback))->toBeTrue()
        ->and(array_map(fn (RegistrationHandle $handle): string => $handle->type(), $listeners))
        ->toBe(['action', 'filter', 'collector'])
        ->and($hooks->remove('Shared', $collector))->toBeTrue()
        ->and($hooks->count('Shared'))->toBe(2);
});

class FilterContext
{
    public function __construct(public string $name)
    {
    }
}
