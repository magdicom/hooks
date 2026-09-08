<?php

use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processors\FlattenProcessor;

test('flatten processor returns an empty list for empty results', function () {
    expect((new FlattenProcessor())->process([], new ProcessingContext('FlattenEmpty')))
        ->toBe([]);
});

test('flatten processor preserves empty callback arrays', function () {
    expect((new FlattenProcessor())->process([[]], new ProcessingContext('FlattenEmptyCallback')))
        ->toBe([]);
});

test('flatten processor combines multiple callback arrays in stable order', function () {
    $results = [
        ['first', ['second-a', 'second-b']],
        ['third'],
        ['fourth', ['fifth']],
    ];

    expect((new FlattenProcessor(0))->process($results, new ProcessingContext('FlattenOrder')))
        ->toBe(['first', ['second-a', 'second-b'], 'third', 'fourth', ['fifth']])
        ->and((new FlattenProcessor(1))->process($results, new ProcessingContext('FlattenOrder')))
        ->toBe(['first', 'second-a', 'second-b', 'third', 'fourth', 'fifth']);
});

test('flatten processor discards associative keys while preserving iteration order', function () {
    $results = [
        ['first' => 'alpha', 'second' => ['beta' => 'beta', 'gamma' => 'gamma']],
        ['third' => 'delta'],
    ];

    expect((new FlattenProcessor(0))->process($results, new ProcessingContext('FlattenKeys')))
        ->toBe(['alpha', ['beta' => 'beta', 'gamma' => 'gamma'], 'delta'])
        ->and((new FlattenProcessor(1))->process($results, new ProcessingContext('FlattenKeys')))
        ->toBe(['alpha', 'beta', 'gamma', 'delta']);
});

test('flatten processor supports depth zero positive and unlimited flattening', function () {
    $results = [
        [1, [2, [3, [4]]]],
        [[5, [6]]],
    ];

    expect((new FlattenProcessor(0))->process($results, new ProcessingContext('FlattenDepth0')))
        ->toBe([1, [2, [3, [4]]], [5, [6]]])
        ->and((new FlattenProcessor(1))->process($results, new ProcessingContext('FlattenDepth1')))
        ->toBe([1, 2, [3, [4]], 5, [6]])
        ->and((new FlattenProcessor(2))->process($results, new ProcessingContext('FlattenDepth2')))
        ->toBe([1, 2, 3, [4], 5, 6])
        ->and((new FlattenProcessor())->process($results, new ProcessingContext('FlattenUnlimited')))
        ->toBe([1, 2, 3, 4, 5, 6]);
});

test('flatten processor rejects invalid depth below minus one', function () {
    expect(fn () => new FlattenProcessor(-2))
        ->toThrow(InvalidArgumentException::class, 'FlattenProcessor depth must be -1 or greater, -2 given.');
});

test('flatten processor rejects invalid top level result types with useful messages', function () {
    $processor = new FlattenProcessor();
    $context = new ProcessingContext('FlattenInvalid');
    $resource = fopen('php://memory', 'rb');

    expect($resource)->not->toBeFalse();

    expect(fn () => $processor->process([1], $context))
        ->toThrow(UnexpectedValueException::class, 'FlattenProcessor expects array results; result at index 0 is int.')
        ->and(fn () => $processor->process([null], $context))
        ->toThrow(UnexpectedValueException::class, 'FlattenProcessor expects array results; result at index 0 is null.')
        ->and(fn () => $processor->process([$resource], $context))
        ->toThrow(UnexpectedValueException::class, 'FlattenProcessor expects array results; result at index 0 is resource (stream).')
        ->and(fn () => $processor->process([new stdClass()], $context))
        ->toThrow(UnexpectedValueException::class, 'FlattenProcessor expects array results; result at index 0 is stdClass.');

    fclose($resource);
});

test('flatten processor integrates with collector processing', function () {
    $hooks = new Hooks();
    $hooks->addCollector('navigation', fn (): array => ['home', ['about', 'team']]);
    $hooks->addCollector('navigation', fn (): array => ['contact']);
    $hooks->setProcessor('navigation', new FlattenProcessor(1));

    expect($hooks->collect('navigation'))->toBe([
        ['home', ['about', 'team']],
        ['contact'],
    ])->and($hooks->process('navigation'))->toBe(['home', 'about', 'team', 'contact']);
});
