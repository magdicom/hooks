<?php

use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->register("Callback", function ($vars) {
    return "Closure";
}, 1)
    ->register("Callback", "simple_function_name", 2)
    ->register("Callback", [FooBar::class, 'isStatic'], 3)
    ->register("Callback", [FooBar::class, 'objectBased'], 4)
    ->register("Callback", [(new FooBar()), 'objectBased'], 5);


class FooBar
{
    public function objectBased($vars)
    {
        return "ObjectMethod";
    }

    public static function isStatic($vars)
    {
        return "StaticMethod";
    }
}

function simple_function_name($vars)
{
    return "SimpleFunction";
}

test('Callback -> toArray', function () use ($hooks) {
    expect($hooks->all("Callback")->toArray())
        ->toBe(["Closure", "SimpleFunction", "StaticMethod", "ObjectMethod", "ObjectMethod"]);
});
