<?php
use Magdicom\Hooks\Hooks;

$hooks = new Hooks;

$hooks->register("Strings", function($vars){
    return "One";
}, 1)
->register("Strings", function ($vars){
    return "Three";
}, 3)
->register("Strings", function ($vars){
    return "Two";
}, 2);

test('strings to string', function () use ($hooks) {
    $this->assertSame("OneTwoThree", $hooks->all("Strings")->toString());
});

test('strings to string with separator', function () use ($hooks) {
    $this->assertSame("One:Two:Three", $hooks->all("Strings")->toString(":"));
});

test('strings to array', function () use ($hooks) {
    $this->assertSame(["One","Two","Three"], $hooks->all("Strings")->toArray());
});

test('first to string', function () use ($hooks) {
    $this->assertSame("One", $hooks->first("Strings")->toString());
});

test('first to array', function () use ($hooks)  {
    $this->assertSame(["One"], $hooks->first("Strings")->toArray());
});

test('last to string', function () use ($hooks) {
    $this->assertSame("Three", $hooks->last("Strings")->toString());
});

test('last to array', function () use ($hooks)  {
    $this->assertSame(["Three"], $hooks->last("Strings")->toArray());
});
