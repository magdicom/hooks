<?php

use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Processor\MergeProcessor;

test('merge processor returns an empty array for empty results', function () {
    expect((new MergeProcessor())->process([], new ProcessingContext('MergeEmpty')))
        ->toBe([]);
});

test('merge processor combines list arrays in callback order', function () {
    $results = [
        ['first', 'second'],
        ['third'],
        ['fourth', 'fifth'],
    ];

    expect((new MergeProcessor())->process($results, new ProcessingContext('MergeLists')))
        ->toBe(['first', 'second', 'third', 'fourth', 'fifth']);
});

test('merge processor uses array merge semantics for associative keys and numeric reindexing', function () {
    $results = [
        ['label' => 'first', 4 => 'alpha'],
        ['label' => 'second', 'mode' => 'sync', 8 => 'beta'],
        ['final' => 'done', 2 => 'gamma'],
    ];

    expect((new MergeProcessor())->process($results, new ProcessingContext('MergeAssociative')))
        ->toBe([
            'label' => 'second',
            0 => 'alpha',
            'mode' => 'sync',
            1 => 'beta',
            'final' => 'done',
            2 => 'gamma',
        ]);
});

test('merge processor does not recursively merge nested arrays', function () {
    $results = [
        ['config' => ['one' => 1], 'flags' => ['a']],
        ['config' => ['two' => 2], 'flags' => ['b']],
    ];

    expect((new MergeProcessor())->process($results, new ProcessingContext('MergeNested')))
        ->toBe([
            'config' => ['two' => 2],
            'flags' => ['b'],
        ]);
});

test('merge processor rejects invalid top level result types with useful messages', function () {
    $processor = new MergeProcessor();
    $context = new ProcessingContext('MergeInvalid');
    $resource = fopen('php://memory', 'rb');

    expect($resource)->not->toBeFalse();

    expect(fn () => $processor->process([1], $context))
        ->toThrow(UnexpectedValueException::class, 'MergeProcessor expects array results; result at index 0 is int.')
        ->and(fn () => $processor->process([null], $context))
        ->toThrow(UnexpectedValueException::class, 'MergeProcessor expects array results; result at index 0 is null.')
        ->and(fn () => $processor->process([$resource], $context))
        ->toThrow(UnexpectedValueException::class, 'MergeProcessor expects array results; result at index 0 is resource (stream).')
        ->and(fn () => $processor->process([new stdClass()], $context))
        ->toThrow(UnexpectedValueException::class, 'MergeProcessor expects array results; result at index 0 is stdClass.');

    fclose($resource);
});

test('merge processor integrates with collector processing', function () {
    $hooks = new Hooks();
    $hooks->addCollector('configuration', fn (): array => ['enabled' => true, 'paths' => ['one']]);
    $hooks->addCollector('configuration', fn (): array => ['paths' => ['two'], 'mode' => 'strict']);
    $hooks->setProcessor('configuration', new MergeProcessor());

    expect($hooks->collect('configuration'))->toBe([
        ['enabled' => true, 'paths' => ['one']],
        ['paths' => ['two'], 'mode' => 'strict'],
    ])->and($hooks->process('configuration'))->toBe([
        'enabled' => true,
        'paths' => ['two'],
        'mode' => 'strict',
    ]);
});
