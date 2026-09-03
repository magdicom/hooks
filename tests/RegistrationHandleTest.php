<?php

use Magdicom\Hooks;
use Magdicom\RegistrationHandle;

test('equal priority listeners keep registration order', function () {
    $hooks = new Hooks();

    $hooks->addCollector('SamePriority', fn () => 'First', 10);
    $hooks->addCollector('SamePriority', fn () => 'Second', 10);
    $hooks->addCollector('SamePriority', fn () => 'Third', 10);

    expect($hooks->collect('SamePriority'))->toBe(['First', 'Second', 'Third']);
});

test('collector registration returns a removable handle', function () {
    $hooks = new Hooks();

    $handle = $hooks->addCollector('Handle', fn () => 'Keep');

    expect($handle)->toBeInstanceOf(RegistrationHandle::class)
        ->and($handle->hookPoint())->toBe('Handle')
        ->and($handle->priority())->toBe(10)
        ->and($handle->id())->toBeInt();
});

test('default priority is ten for all hook types', function () {
    $hooks = new Hooks();

    $action = $hooks->addAction('ActionDefault', fn (): null => null);
    $filter = $hooks->addFilter('FilterDefault', fn (string $value): string => $value);
    $collector = $hooks->addCollector('CollectorDefault', fn (): string => 'value');

    expect($action->priority())->toBe(10)
        ->and($filter->priority())->toBe(10)
        ->and($collector->priority())->toBe(10);
});

test('closure listeners can be removed through handles', function () {
    $hooks = new Hooks();

    $removed = $hooks->addCollector('Removal', fn () => 'Remove me', 1);
    $hooks->addCollector('Removal', fn () => 'Keep me', 2);

    expect($removed->remove())->toBeTrue()
        ->and($removed->remove())->toBeFalse()
        ->and($hooks->collect('Removal'))->toBe(['Keep me']);
});

test('has and count reflect hook registrations', function () {
    $hooks = new Hooks();
    $callback = fn () => 'Two';

    $first = $hooks->addCollector('Inspect', fn () => 'One', 5);
    $hooks->addCollector('Inspect', $callback, 10);

    expect($hooks->has('Inspect'))->toBeTrue()
        ->and($hooks->has('Missing'))->toBeFalse()
        ->and($hooks->hasCollector('Inspect', $callback))->toBeTrue()
        ->and($hooks->hasCollector('Inspect', $callback, 20))->toBeFalse()
        ->and(array_map(fn (RegistrationHandle $handle): int => $handle->id(), $hooks->collectors('Inspect')))
        ->toContain($first->id())
        ->and($hooks->count('Inspect'))->toBe(2)
        ->and($hooks->count())->toBeGreaterThanOrEqual(2);
});

test('listeners returns ordered handles for a hook', function () {
    $hooks = new Hooks();

    $second = $hooks->addCollector('Listened', fn () => 'Second', 20);
    $first = $hooks->addCollector('Listened', fn () => 'First', 10);

    $listeners = $hooks->listeners('Listened');

    expect(array_map(fn (RegistrationHandle $handle): int => $handle->id(), $listeners))
        ->toBe([$first->id(), $second->id()])
        ->and(array_map(fn (RegistrationHandle $handle): int => $handle->priority(), $listeners))
        ->toBe([10, 20]);
});

test('typed callback removal is priority-aware and handle removal stays exact', function () {
    $hooks = new Hooks();
    $callback = fn () => 'Callback';
    $handle = $hooks->addCollector('RemoveBy', fn () => 'Handle', 5);
    $hooks->addCollector('RemoveBy', $callback, 10);
    $hooks->addCollector('RemoveBy', $callback, 20);

    expect($hooks->removeCollector('RemoveBy', $callback))->toBeTrue()
        ->and($hooks->collect('RemoveBy'))->toBe(['Handle', 'Callback'])
        ->and($hooks->hasCollector('RemoveBy', $callback))->toBeFalse()
        ->and($hooks->hasCollector('RemoveBy', $callback, 20))->toBeTrue()
        ->and($hooks->removeCollector('RemoveBy', $callback, 20))->toBeTrue()
        ->and($hooks->hasCollector('RemoveBy', $callback, 20))->toBeFalse()
        ->and($handle->remove())->toBeTrue()
        ->and($hooks->has('RemoveBy'))->toBeFalse();
});

test('handles from another hooks instance never match local registrations', function () {
    $firstHooks = new Hooks();
    $secondHooks = new Hooks();

    $foreignHandle = $firstHooks->addCollector('Shared', fn (): string => 'foreign');
    $localCallback = fn (): string => 'local';
    $secondHooks->addCollector('Shared', $localCallback);

    expect($secondHooks->hasCollector('Shared', $localCallback))->toBeTrue()
        ->and($secondHooks->collect('Shared'))->toBe(['local'])
        ->and($foreignHandle->remove())->toBeTrue()
        ->and($secondHooks->collect('Shared'))->toBe(['local'])
        ->and($firstHooks->collect('Shared'))->toBe([]);
});

test('type-aware removals do not cross hook models', function () {
    $hooks = new Hooks();
    $callback = fn (): string => 'value';

    $actionCallback = fn (): string => 'value';

    $hooks->addAction('SharedType', $actionCallback);
    $hooks->addCollector('SharedType', $callback);

    expect($hooks->removeAction('SharedType', $actionCallback))->toBeTrue()
        ->and($hooks->hasAction('SharedType'))->toBeFalse()
        ->and($hooks->hasCollector('SharedType', $callback))->toBeTrue()
        ->and($hooks->removeCollector('SharedType', $callback))->toBeTrue()
        ->and($hooks->has('SharedType'))->toBeFalse();
});

test('callback-specific has checks are priority-aware', function () {
    $hooks = new Hooks();
    $callback = fn (): string => 'value';

    $hooks->addCollector('PriorityAwareHas', $callback, 10);
    $hooks->addCollector('PriorityAwareHas', $callback, 20);

    expect($hooks->hasCollector('PriorityAwareHas', $callback))->toBeTrue()
        ->and($hooks->hasCollector('PriorityAwareHas', $callback, 20))->toBeTrue()
        ->and($hooks->removeCollector('PriorityAwareHas', $callback))->toBeTrue()
        ->and($hooks->hasCollector('PriorityAwareHas', $callback))->toBeFalse()
        ->and($hooks->hasCollector('PriorityAwareHas', $callback, 20))->toBeTrue();
});

test('removeAll clears one hook or the entire registry', function () {
    $hooks = new Hooks();

    $hooks->addCollector('FirstHook', fn () => 'One', 1);
    $hooks->addCollector('FirstHook', fn () => 'Two', 2);
    $hooks->addCollector('SecondHook', fn () => 'Three', 1);

    expect($hooks->removeAll('FirstHook'))->toBe(2)
        ->and($hooks->count('FirstHook'))->toBe(0)
        ->and($hooks->count())->toBe(1)
        ->and($hooks->removeAll())->toBe(1)
        ->and($hooks->count())->toBe(0)
        ->and($hooks->listeners('SecondHook'))->toBe([]);
});
