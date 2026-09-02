<?php

use Magdicom\Hooks;

test('action dispatch uses a listener snapshot when listeners are removed during execution', function () {
    $hooks = new Hooks();
    $events = [];

    $removed = $hooks->addAction('ActionMutate', function () use (&$events): void {
        $events[] = 'second';
    }, 20);

    $hooks->addAction('ActionMutate', function () use (&$events, $hooks, $removed): void {
        $events[] = 'first';
        $hooks->removeAction('ActionMutate', $removed);
    }, 10);

    $hooks->doAction('ActionMutate');
    $hooks->doAction('ActionMutate');

    expect($events)->toBe(['first', 'second', 'first']);
});

test('collector dispatch uses a listener snapshot when listeners are added during execution', function () {
    $hooks = new Hooks();
    $events = [];

    $hooks->addCollector('CollectorMutateAdd', function () use (&$events, $hooks): string {
        $events[] = 'first';
        $hooks->addCollector('CollectorMutateAdd', function () use (&$events): string {
            $events[] = 'late';

            return 'late';
        }, 30);

        return 'first';
    }, 10);

    $hooks->addCollector('CollectorMutateAdd', function () use (&$events): string {
        $events[] = 'second';

        return 'second';
    }, 20);

    expect($hooks->collect('CollectorMutateAdd'))->toBe(['first', 'second'])
        ->and($events)->toBe(['first', 'second'])
        ->and($hooks->collect('CollectorMutateAdd'))->toBe(['first', 'second', 'late'])
        ->and($events)->toBe(['first', 'second', 'first', 'second', 'late']);
});

test('filter dispatch applies additions on the next invocation only', function () {
    $hooks = new Hooks();

    $hooks->addFilter('FilterMutate', function (string $value) use ($hooks): string {
        $hooks->addFilter('FilterMutate', fn (string $next): string => $next . 'C', 30);

        return $value . 'A';
    }, 10);

    $hooks->addFilter('FilterMutate', fn (string $value): string => $value . 'B', 20);

    expect($hooks->applyFilters('FilterMutate', ''))->toBe('AB')
        ->and($hooks->applyFilters('FilterMutate', ''))->toBe('ABC');
});

test('collector dispatch applies removals on the next invocation only', function () {
    $hooks = new Hooks();

    $removed = $hooks->addCollector('CollectorMutate', fn (): string => 'second', 20);

    $hooks->addCollector('CollectorMutate', function () use ($hooks, $removed): string {
        $hooks->removeCollector('CollectorMutate', $removed);

        return 'first';
    }, 10);

    expect($hooks->collect('CollectorMutate'))->toBe(['first', 'second'])
        ->and($hooks->collect('CollectorMutate'))->toBe(['first']);
});
