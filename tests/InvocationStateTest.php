<?php

use Magdicom\Hooks;

test('repeated collector invocations stay independent', function () {
    $hooks = new Hooks();

    $hooks->addCollector('Repeated', fn () => 'Alpha', 1);
    $hooks->addCollector('Repeated', fn () => 'Beta', 2);

    expect($hooks->collect('Repeated'))->toBe(['Alpha', 'Beta'])
        ->and($hooks->collect('Repeated'))->toBe(['Alpha', 'Beta']);
});

test('nested collector invocations stay isolated', function () {
    $hooks = new Hooks();

    $hooks->addCollector('Inner', fn () => 'NestedA', 1);
    $hooks->addCollector('Inner', fn () => 'NestedB', 2);

    $hooks->addCollector('Outer', fn () => 'OuterA', 1);
    $hooks->addCollector('Outer', function () use ($hooks) {
        expect($hooks->collect('Inner'))->toBe(['NestedA', 'NestedB']);

        return 'OuterB';
    }, 2);
    $hooks->addCollector('Outer', fn () => 'OuterC', 3);

    expect($hooks->collect('Outer'))->toBe(['OuterA', 'OuterB', 'OuterC']);
});

test('recursive collector invocations stay isolated', function () {
    $hooks = new Hooks();

    $hooks->addCollector('Recursive', function (int $depth) use ($hooks) {
        $currentDepth = $depth;

        if ($currentDepth < 2) {
            expect($hooks->collect('Recursive', $currentDepth + 1))
                ->toBe(['depth-' . ($currentDepth + 1)]);
        }

        return 'depth-' . $currentDepth;
    });

    expect($hooks->collect('Recursive', 0))->toBe(['depth-0']);
});

test('nested action filter and collector dispatches stay isolated', function () {
    $hooks = new Hooks();
    $events = [];

    $hooks->addAction('InnerAction', function () use (&$events) {
        $events[] = 'inner';
    });

    $hooks->addAction('OuterAction', function () use ($hooks, &$events) {
        $events[] = 'before';
        $hooks->doAction('InnerAction');
        $events[] = 'after';
    });

    $hooks->addFilter('InnerFilter', fn (string $value): string => $value . '-inner');
    $hooks->addFilter('OuterFilter', fn (string $value): string => $hooks->applyFilters('InnerFilter', $value) . '-outer');

    $hooks->addCollector('InnerCollector', fn (): string => 'inner-result');
    $hooks->addCollector('OuterCollector', fn (): array => $hooks->collect('InnerCollector'));

    $hooks->doAction('OuterAction');

    expect($events)->toBe(['before', 'inner', 'after'])
        ->and($hooks->applyFilters('OuterFilter', 'start'))->toBe('start-inner-outer')
        ->and($hooks->collect('OuterCollector'))->toBe([['inner-result']]);
});

test('recursive filters complete without leaking state across invocations', function () {
    $hooks = new Hooks();

    $hooks->addFilter('RecursiveFilter', function (int $value) use ($hooks): int {
        if ($value >= 3) {
            return $value;
        }

        return $hooks->applyFilters('RecursiveFilter', $value + 1);
    });

    expect($hooks->applyFilters('RecursiveFilter', 0))->toBe(3)
        ->and($hooks->applyFilters('RecursiveFilter', 2))->toBe(3);
});
