<?php

use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processor\BooleanAndProcessor;
use Magdicom\Processor\BooleanOrProcessor;

test('boolean and processor uses logical identity for empty results', function () {
    expect((new BooleanAndProcessor())->process([], new ProcessingContext('AndEmpty')))
        ->toBeTrue();
});

test('boolean or processor uses logical identity for empty results', function () {
    expect((new BooleanOrProcessor())->process([], new ProcessingContext('OrEmpty')))
        ->toBeFalse();
});

test('boolean processors handle true and false combinations strictly', function () {
    $and = new BooleanAndProcessor();
    $or = new BooleanOrProcessor();
    $context = new ProcessingContext('BooleanValues');

    expect($and->process([true, true], $context))->toBeTrue()
        ->and($and->process([true, false], $context))->toBeFalse()
        ->and($and->process([false, false], $context))->toBeFalse()
        ->and($or->process([false, false], $context))->toBeFalse()
        ->and($or->process([true, false], $context))->toBeTrue()
        ->and($or->process([false, true], $context))->toBeTrue();
});

test('boolean processors reject non boolean results with useful messages', function () {
    $and = new BooleanAndProcessor();
    $or = new BooleanOrProcessor();
    $context = new ProcessingContext('BooleanInvalid');

    $cases = [
        [1, 'int'],
        [0, 'int'],
        ['yes', 'string'],
        ['', 'string'],
        [null, 'null'],
        [[true], 'array'],
        [new stdClass(), 'stdClass'],
    ];

    foreach ($cases as [$value, $type]) {
        expect(fn () => $and->process([$value], $context))
            ->toThrow(UnexpectedValueException::class, sprintf(
                'BooleanAndProcessor expects boolean results; result at index 0 is %s.',
                $type
            ));

        expect(fn () => $or->process([$value], $context))
            ->toThrow(UnexpectedValueException::class, sprintf(
                'BooleanOrProcessor expects boolean results; result at index 0 is %s.',
                $type
            ));
    }
});

test('boolean processors integrate with collector processing', function () {
    $hooks = new Hooks();
    $hooks->addCollector('requirements-and', fn (): bool => true);
    $hooks->addCollector('requirements-and', fn (): bool => false);
    $hooks->setProcessor('requirements-and', new BooleanAndProcessor());

    $hooks->addCollector('requirements-or', fn (): bool => false);
    $hooks->addCollector('requirements-or', fn (): bool => true);
    $hooks->setProcessor('requirements-or', new BooleanOrProcessor());

    expect($hooks->collect('requirements-and'))->toBe([true, false])
        ->and($hooks->process('requirements-and'))->toBeFalse()
        ->and($hooks->collect('requirements-or'))->toBe([false, true])
        ->and($hooks->process('requirements-or'))->toBeTrue();
});

test('all collector callbacks run before boolean processing begins', function () {
    $events = [];
    $hooks = new Hooks();

    $hooks->addCollector('requirements-and', function () use (&$events): bool {
        $events[] = 'and:first';

        return false;
    }, 10);
    $hooks->addCollector('requirements-and', function () use (&$events): bool {
        $events[] = 'and:second';

        return true;
    }, 20);
    $hooks->addCollector('requirements-and', function () use (&$events): bool {
        $events[] = 'and:third';

        return true;
    }, 30);
    $hooks->setProcessor('requirements-and', new BooleanAndProcessor());

    $hooks->addCollector('requirements-or', function () use (&$events): bool {
        $events[] = 'or:first';

        return true;
    }, 10);
    $hooks->addCollector('requirements-or', function () use (&$events): bool {
        $events[] = 'or:second';

        return false;
    }, 20);
    $hooks->addCollector('requirements-or', function () use (&$events): bool {
        $events[] = 'or:third';

        return false;
    }, 30);
    $hooks->setProcessor('requirements-or', new BooleanOrProcessor());

    expect($hooks->process('requirements-and'))->toBeFalse()
        ->and($hooks->process('requirements-or'))->toBeTrue()
        ->and($events)->toBe([
            'and:first',
            'and:second',
            'and:third',
            'or:first',
            'or:second',
            'or:third',
        ]);
});
