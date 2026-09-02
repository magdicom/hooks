<?php

use Magdicom\Hooks;

test('actions with no listeners complete without side effects', function () {
    $hooks = new Hooks();

    $hooks->doAction('MissingAction', 'value', 1, true);

    expect($hooks->has('MissingAction'))->toBeFalse();
});

test('filters with no listeners return the original value', function () {
    $hooks = new Hooks();

    expect($hooks->applyFilters('MissingFilter', 'original', 'ignored'))
        ->toBe('original');
});

test('collectors with no listeners return an empty array', function () {
    $hooks = new Hooks();

    expect($hooks->collect('MissingCollector', 'ignored'))
        ->toBe([]);
});
