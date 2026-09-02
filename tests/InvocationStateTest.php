<?php

use Magdicom\Hooks;

test('repeated invocations keep isolated results', function () {
    $hooks = new Hooks();

    $hooks->register('Repeated', fn () => 'Alpha', 1)
        ->register('Repeated', fn () => 'Beta', 2);

    expect($hooks->all('Repeated')->toArray())->toBe(['Alpha', 'Beta'])
        ->and($hooks->first('Repeated')->toArray())->toBe(['Alpha'])
        ->and($hooks->all('Repeated')->toArray())->toBe(['Alpha', 'Beta'])
        ->and($hooks->last('Repeated')->toArray())->toBe(['Beta']);
});

test('nested invocations do not corrupt parent results', function () {
    $hooks = new Hooks();

    $hooks->register('Inner', fn () => 'NestedA', 1)
        ->register('Inner', fn () => 'NestedB', 2);

    $hooks->register('Outer', fn () => 'OuterA', 1)
        ->register('Outer', function () use ($hooks) {
            expect($hooks->all('Inner')->toString(':'))->toBe('NestedA:NestedB');

            return 'OuterB';
        }, 2)
        ->register('Outer', fn () => 'OuterC', 3);

    expect($hooks->all('Outer')->toArray())->toBe(['OuterA', 'OuterB', 'OuterC'])
        ->and($hooks->toString(':'))->toBe('OuterA:OuterB:OuterC');
});

test('recursive legacy invocations restore the parent result state', function () {
    $hooks = new Hooks();

    $hooks->register('Recursive', function (array $vars) use ($hooks) {
        $depth = $vars['depth'] ?? 0;

        if ($depth < 2) {
            expect($hooks->all('Recursive', ['depth' => $depth + 1])->toArray())
                ->toBe(['depth-' . ($depth + 1)]);
        }

        return 'depth-' . $depth;
    });

    expect($hooks->all('Recursive', ['depth' => 0])->toArray())->toBe(['depth-0'])
        ->and($hooks->toString())->toBe('depth-0');
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

    expect($hooks->doAction('OuterAction'))->toBe($hooks)
        ->and($events)->toBe(['before', 'inner', 'after'])
        ->and($hooks->applyFilters('OuterFilter', 'start'))->toBe('start-inner-outer')
        ->and($hooks->collect('OuterCollector'))->toBe([['inner-result']])
        ->and($hooks->toArray())->toBe([]);
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
        ->and($hooks->applyFilters('RecursiveFilter', 2))->toBe(3)
        ->and($hooks->toArray())->toBe([]);
});
