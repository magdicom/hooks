<?php

use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->setParameters([
    "id" => "Foo",
    "name" => "Bar",
]);

$hooks->register("Parameters", function ($vars) use ($hooks) {
    $hooks->setParameter("email", "baz@email.com");

    return $vars;
}, 1);

test('Parameters -> add', function () use ($hooks) {
    expect($hooks->all("Parameters")->toArray())
        ->toBe(["id" => "Foo", "name" => "Bar", "email" => "baz@email.com"]);
});

$hooks->register("Parameters", function ($vars) {
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

# Passing Object As The Parameter
$hooks->register("ParameterAsObject", function ($fooBarBaz, $params) {
    return [$fooBarBaz->getId(), $params['name']];
}, 2);

test('Parameters as Object', function () use ($hooks) {
    expect($hooks->all("ParameterAsObject", (new FooBarBaz(100)))->toArray())
        ->toBe([100, "Bar"]);
});

class FooBarBaz {
    public $id;

    public function __construct(int $id){
        $this->id = $id;
    }

    public function getId(){
        return $this->id;
    }
}
