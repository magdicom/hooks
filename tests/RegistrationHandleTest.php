<?php

use Magdicom\Hooks;
use Magdicom\RegistrationHandle;

test('equal priority listeners keep registration order', function () {
    $hooks = new Hooks();

    $hooks->register('SamePriority', fn () => 'First', 10)
        ->register('SamePriority', fn () => 'Second', 10)
        ->register('SamePriority', fn () => 'Third', 10);

    expect($hooks->all('SamePriority')->toArray())->toBe(['First', 'Second', 'Third']);
});

test('register returns a removable handle', function () {
    $hooks = new Hooks();

    $handle = $hooks->register('Handle', fn () => 'Keep', 1);

    expect($handle)->toBeInstanceOf(RegistrationHandle::class)
        ->and($handle->hookPoint())->toBe('Handle')
        ->and($handle->priority())->toBe(1)
        ->and($handle->id())->toBeInt();
});

test('closure listeners can be removed through handles', function () {
    $hooks = new Hooks();

    $removed = $hooks->register('Removal', fn () => 'Remove me', 1);
    $hooks->register('Removal', fn () => 'Keep me', 2);

    expect($removed->remove())->toBeTrue()
        ->and($removed->remove())->toBeFalse()
        ->and($hooks->all('Removal')->toArray())->toBe(['Keep me']);
});

test('has and count reflect hook registrations', function () {
    $hooks = new Hooks();
    $callback = fn () => 'Two';

    $first = $hooks->register('Inspect', fn () => 'One', 5);
    $hooks->register('Inspect', $callback, 10);

    expect($hooks->has('Inspect'))->toBeTrue()
        ->and($hooks->has('Missing'))->toBeFalse()
        ->and($hooks->has('Inspect', $first))->toBeTrue()
        ->and($hooks->has('Inspect', $callback))->toBeTrue()
        ->and($hooks->count('Inspect'))->toBe(2)
        ->and($hooks->count())->toBeGreaterThanOrEqual(2);
});

test('listeners returns ordered handles for a hook', function () {
    $hooks = new Hooks();

    $second = $hooks->register('Listened', fn () => 'Second', 20);
    $first = $hooks->register('Listened', fn () => 'First', 10);

    $listeners = $hooks->listeners('Listened');

    expect(array_map(fn (RegistrationHandle $handle): int => $handle->id(), $listeners))
        ->toBe([$first->id(), $second->id()])
        ->and(array_map(fn (RegistrationHandle $handle): int => $handle->priority(), $listeners))
        ->toBe([10, 20]);
});

test('remove supports callback and handle removal', function () {
    $hooks = new Hooks();
    $callback = fn () => 'Callback';
    $handle = $hooks->register('RemoveBy', fn () => 'Handle', 5);
    $hooks->register('RemoveBy', $callback, 10);

    expect($hooks->remove('RemoveBy', $callback))->toBeTrue()
        ->and($hooks->remove('RemoveBy', $callback))->toBeFalse()
        ->and($hooks->remove('RemoveBy', $handle))->toBeTrue()
        ->and($hooks->has('RemoveBy'))->toBeFalse();
});

test('removeAll clears one hook or the entire registry', function () {
    $hooks = new Hooks();

    $hooks->register('FirstHook', fn () => 'One', 1);
    $hooks->register('FirstHook', fn () => 'Two', 2);
    $hooks->register('SecondHook', fn () => 'Three', 1);

    expect($hooks->removeAll('FirstHook'))->toBe(2)
        ->and($hooks->count('FirstHook'))->toBe(0)
        ->and($hooks->count())->toBe(1)
        ->and($hooks->removeAll())->toBe(1)
        ->and($hooks->count())->toBe(0)
        ->and($hooks->listeners('SecondHook'))->toBe([]);
});
