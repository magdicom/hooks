<?php

use Magdicom\Hooks;

test('actions receive variadic invocation arguments', function () {
    $hooks = new Hooks();
    $events = [];

    $hooks->addAction('ActionArgs', function (string $name, int $count) use (&$events): void {
        $events[] = $name . ':' . $count;
    });

    $hooks->doAction('ActionArgs', 'hooks', 2);

    expect($events)->toBe(['hooks:2']);
});

test('collectors receive variadic invocation arguments', function () {
    $hooks = new Hooks();

    $hooks->addCollector('CollectorArgs', function (string $name, bool $enabled): array {
        return ['name' => $name, 'enabled' => $enabled];
    });

    expect($hooks->collect('CollectorArgs', 'hooks', true))
        ->toBe([['name' => 'hooks', 'enabled' => true]]);
});

test('filters receive the current value followed by variadic invocation arguments', function () {
    $hooks = new Hooks();

    $hooks->addFilter('FilterArgs', function (string $value, string $suffix, int $repeat): string {
        return $value . str_repeat($suffix, $repeat);
    });

    expect($hooks->applyFilters('FilterArgs', 'hook', '!', 2))->toBe('hook!!');
});

test('typed context objects can be passed explicitly to collectors and filters', function () {
    $hooks = new Hooks();

    $hooks->addCollector('CollectorContext', function (ArgumentContext $context, string $label): array {
        return [$context->id, $label];
    });

    $hooks->addFilter('FilterContext', function (string $value, ArgumentContext $context): string {
        return $value . ':' . $context->id;
    });

    $context = new ArgumentContext(100);

    expect($hooks->collect('CollectorContext', $context, 'ready'))->toBe([[100, 'ready']])
        ->and($hooks->applyFilters('FilterContext', 'value', $context))->toBe('value:100');
});

class ArgumentContext
{
    public function __construct(public int $id)
    {
    }
}
