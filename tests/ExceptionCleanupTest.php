<?php

use Magdicom\Hooks;

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

    expect($events)->toBe(['before-throw']);

    $hooks->doAction('SafeAction');

    expect($events)->toBe(['before-throw', 'safe-action'])
        ->and($hooks->applyFilters('SafeFilter', 'value'))->toBe('value-safe')
        ->and($hooks->collect('SafeCollector'))->toBe(['safe-collector']);
});

test('nested collector exceptions do not break later collector dispatches', function () {
    $hooks = new Hooks();

    $hooks->addCollector('ExplodeCollector', function () use ($hooks): string {
        try {
            $hooks->collect('InnerExplode');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->toBe('boom');
        }

        return 'recovered';
    });

    $hooks->addCollector('InnerExplode', function (): string {
        throw new RuntimeException('boom');
    });

    $hooks->addCollector('SafeCollector', fn (): string => 'safe');

    expect($hooks->collect('ExplodeCollector'))->toBe(['recovered'])
        ->and($hooks->collect('SafeCollector'))->toBe(['safe']);
});
