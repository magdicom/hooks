<?php

use Magdicom\Hooks;

test('nested legacy exceptions restore parent result state', function () {
    $hooks = new Hooks();

    $hooks->register('Explode', fn () => 'explode-start', 10)
        ->register('Explode', function (): void {
            throw new RuntimeException('boom');
        }, 20);

    $hooks->register('Outer', fn () => 'outer-start', 10)
        ->register('Outer', function () use ($hooks) {
            try {
                $hooks->all('Explode');
            } catch (RuntimeException $exception) {
                expect($exception->getMessage())->toBe('boom');
            }

            return 'outer-recovered';
        }, 20)
        ->register('Outer', fn () => 'outer-end', 30);

    $hooks->register('Safe', fn () => 'safe');

    expect($hooks->all('Outer')->toArray())->toBe([
        'outer-start',
        'outer-recovered',
        'outer-end',
    ])->and($hooks->toString(':'))->toBe('outer-start:outer-recovered:outer-end')
        ->and($hooks->all('Safe')->toArray())->toBe(['safe']);
});

test('failed action filter and collector dispatches leave later invocations usable', function () {
    $hooks = new Hooks();
    $events = [];

    $hooks->addAction('ExplodeAction', function () use (&$events) {
        $events[] = 'before-throw';

        throw new RuntimeException('action failure');
    });

    $hooks->addFilter('ExplodeFilter', function (string $value): string {
        throw new RuntimeException($value . ' failure');
    });

    $hooks->addCollector('ExplodeCollector', function (): string {
        throw new RuntimeException('collector failure');
    });

    try {
        $hooks->doAction('ExplodeAction');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('action failure');
    }

    try {
        $hooks->applyFilters('ExplodeFilter', 'filter');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('filter failure');
    }

    try {
        $hooks->collect('ExplodeCollector');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('collector failure');
    }

    $hooks->addAction('SafeAction', function () use (&$events) {
        $events[] = 'safe-action';
    });

    $hooks->addFilter('SafeFilter', fn (string $value): string => $value . '-safe');
    $hooks->addCollector('SafeCollector', fn (): string => 'safe-collector');

    expect($events)->toBe(['before-throw'])
        ->and($hooks->doAction('SafeAction'))->toBe($hooks)
        ->and($events)->toBe(['before-throw', 'safe-action'])
        ->and($hooks->applyFilters('SafeFilter', 'value'))->toBe('value-safe')
        ->and($hooks->collect('SafeCollector'))->toBe(['safe-collector'])
        ->and($hooks->toArray())->toBe([]);
});
