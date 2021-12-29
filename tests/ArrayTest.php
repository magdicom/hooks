<?php

use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->register("KeyValuePairs", function ($vars) {
    return ["id" => "Foo"];
}, 1)
->register("KeyValuePairs", function ($vars) {
    return ["id" => "Baz"];
}, 3)
->register("KeyValuePairs", function ($vars) {
    return ["id" => "Bar", "name" => "John Doe"];
}, 2);

test('KeyValuePairs -> all -> toArray', function () use ($hooks) {
    expect($hooks->all("KeyValuePairs")->toArray())->toBe(["id" => "Baz", "name" => "John Doe"]);
});

test('KeyValuePairs -> first -> toArray', function () use ($hooks) {
    expect($hooks->first("KeyValuePairs")->toArray())->toBe(["id" => "Foo"]);
});

test('KeyValuePairs -> last -> toArray', function () use ($hooks) {
    expect($hooks->last("KeyValuePairs")->toArray())->toBe(["id" => "Baz"]);
});

$hooks->register("Array", function ($vars) {
    return [["id" => "Foo"]];
}, 1)
->register("Array", function ($vars) {
    return [["id" => "Baz"]];
}, 3)
->register("Array", function ($vars) {
    return [["id" => "Bar"]];
}, 2);

test('array -> all -> toArray', function () use ($hooks) {
    expect($hooks->all("Array")->toArray())->toBe([["id" => "Foo"], ["id" => "Bar"], ["id" => "Baz"]]);
});

test('array -> first -> toArray', function () use ($hooks) {
    expect($hooks->first("Array")->toArray())->toBe([["id" => "Foo"]]);
});

test('array -> last -> toArray', function () use ($hooks) {
    expect($hooks->last("Array")->toArray())->toBe([["id" => "Baz"]]);
});
