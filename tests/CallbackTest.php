<?php

use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->addCollector("Callback", function () {
    return "Closure";
}, 1);
$hooks->addCollector("Callback", "simple_function_name", 2);
$hooks->addCollector("Callback", [FooBar::class, 'isStatic'], 3);
$hooks->addCollector("Callback", [FooBar::class, 'objectBased'], 4);
$hooks->addCollector("Callback", [(new FooBar()), 'objectBased'], 5);


class FooBar
{
    public function objectBased()
    {
        return "ObjectMethod";
    }

    public static function isStatic()
    {
        return "StaticMethod";
    }
}

function simple_function_name()
{
    return "SimpleFunction";
}

test('collector callbacks support closures functions and method arrays', function () use ($hooks) {
    expect($hooks->collect("Callback"))
        ->toBe(["Closure", "SimpleFunction", "StaticMethod", "ObjectMethod", "ObjectMethod"]);
});
