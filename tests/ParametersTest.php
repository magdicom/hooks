<?php
use Magdicom\Hooks\Hooks;

$hooks = new Hooks;

$hooks->setParameters([
    "id" => "Foo",
    "name" => "Bar"
]);

$hooks->register("Parameters", function($vars) use ($hooks) {

    $hooks->setParameter("email", "baz@email.com");

    return $vars;
}, 1);

test('Parameters -> add', function () use ($hooks) {
    expect($hooks->all("Parameters")->toArray())
        ->toBe(["id" => "Foo", "name" => "Bar", "email" => "baz@email.com"]);
});

$hooks->register("Parameters", function($vars){
    return $vars;
}, 2);

test('Parameters -> temporary', function () use ($hooks) {
    expect($hooks->all("Parameters", ["email" => "qux@email.com", "extra" => "quux"])->toArray())
        ->toBe(["id" => "Foo", "name" => "Bar", "email" => "qux@email.com", "extra" => "quux"]);
});

test('Parameters -> permanent', function () use ($hooks) {
    expect($hooks->all("Parameters")->toArray())
        ->toBe(["id" => "Foo", "name" => "Bar", "email" => "baz@email.com"]);
});
