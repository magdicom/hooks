<?php

use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->register("Strings", function ($vars) {
    return "Foo";
}, 1)
->register("Strings", function ($vars) {
    return "Baz";
}, 3)
->register("Strings", function ($vars) {
    return "Bar";
}, 2);

test('strings to string', function () use ($hooks) {
    expect($hooks->all("Strings")->toString())->toBe("FooBarBaz");
});

test('strings to string with separator', function () use ($hooks) {
    expect($hooks->all("Strings")->toString(":"))->toBe("Foo:Bar:Baz");
});

test('strings to array', function () use ($hooks) {
    expect($hooks->all("Strings")->toArray())->toBe(["Foo","Bar","Baz"]);
});

test('first to string', function () use ($hooks) {
    expect($hooks->first("Strings")->toString())->toBe("Foo");
});

test('first to array', function () use ($hooks) {
    expect($hooks->first("Strings")->toArray())->toBe(["Foo"]);
});

test('last to string', function () use ($hooks) {
    expect($hooks->last("Strings")->toString())->toBe("Baz");
});

test('last to array', function () use ($hooks) {
    expect($hooks->last("Strings")->toArray())->toBe(["Baz"]);
});
