# PHP Hooks

[![Latest Version on Packagist](https://img.shields.io/packagist/v/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)
[![Tests](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml/badge.svg?branch=main)](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)

`magdicom/hooks` is a lightweight, framework-independent hook system for PHP.

Version `2.0` is an intentionally breaking release. If you are upgrading from `1.x`, read [UPGRADE.md](UPGRADE.md) before migrating code.

The `2.0` branch currently exposes three explicit hook models:

- actions for side effects
- filters for sequential value transformation
- collectors for raw result gathering

## Installation

This package currently targets PHP `8.2` or newer.

```bash
composer require magdicom/hooks
```

## Quick Start

### Actions

```php
use Magdicom\Hooks;

$hooks = new Hooks();
$events = [];

$hooks->addAction('boot', function (string $name) use (&$events): void {
    $events[] = 'prepare:' . $name;
}, 10);

$hooks->addAction('boot', function (string $name) use (&$events): void {
    $events[] = 'finish:' . $name;
}, 20);

$hooks->doAction('boot', 'hooks');

var_dump($events);
```

### Filters

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->addFilter('title', fn (string $value): string => trim($value), 10);
$hooks->addFilter('title', fn (string $value, string $suffix): string => $value . $suffix, 20);
$hooks->addFilter('title', fn (string $value): string => strtoupper($value), 30);

echo $hooks->applyFilters('title', ' hello ', '!');
```

### Collectors

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->addCollector('report', fn (): array => ['id' => 1], 10);
$hooks->addCollector('report', fn (): array => ['id' => 2], 20);
$hooks->addCollector('report', fn (): string => 'done', 30);

var_dump($hooks->collect('report'));
```

## Registration and Removal

All registration APIs return a `RegistrationHandle`.
If no priority is provided, the default is `10`.

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$handle = $hooks->addAction('boot', fn (): null => null, 10);

var_dump($hooks->has('boot'));
var_dump($hooks->hasAction('boot', $handle));
var_dump($hooks->count('boot'));
var_dump($hooks->listeners('boot'));
var_dump($hooks->actions('boot'));

$handle->remove();
```

Available inspection and removal methods:

- `has(string $hookName): bool`
- `hasAction(string $hookName, RegistrationHandle|array|callable|null $listener = null): bool`
- `hasFilter(string $hookName, RegistrationHandle|array|callable|null $listener = null): bool`
- `hasCollector(string $hookName, RegistrationHandle|array|callable|null $listener = null): bool`
- `count(?string $hookName = null): int`
- `listeners(string $hookName): array`
- `actions(string $hookName): array`
- `filters(string $hookName): array`
- `collectors(string $hookName): array`
- `removeAction(string $hookName, RegistrationHandle|array|callable $listener): bool`
- `removeFilter(string $hookName, RegistrationHandle|array|callable $listener): bool`
- `removeCollector(string $hookName, RegistrationHandle|array|callable $listener): bool`
- `removeAll(?string $hookName = null): int`
- `removeAllActions(?string $hookName = null): int`
- `removeAllFilters(?string $hookName = null): int`
- `removeAllCollectors(?string $hookName = null): int`

## Ordering and Dispatch Safety

- Lower numeric priorities run before higher numeric priorities.
- The default listener priority is `10`.
- When priorities are equal, listeners keep their registration order.
- Listener additions or removals during dispatch affect the next invocation, not the current one.
- Nested and recursive executions are isolated from each other.
- Exceptions bubble to the caller without corrupting later invocations.

## Invocation Arguments

Invocation uses natural variadic arguments:

- `doAction(string $hookName, mixed ...$arguments): void`
- `applyFilters(string $hookName, mixed $value, mixed ...$arguments): mixed`
- `collect(string $hookName, mixed ...$arguments): array`

Action and collector callbacks receive `...$arguments` exactly as passed.

Filter callbacks receive the current filtered value first, followed by `...$arguments`.

### Scalar Arguments

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->addAction('greet', function (string $prefix, string $name): void {
    echo $prefix . ' ' . $name;
});

$hooks->doAction('greet', 'Hello', 'world');
```

### Typed Context Objects

```php
use Magdicom\Hooks;

class GreetingContext
{
    public function __construct(public int $id)
    {
    }
}

$hooks = new Hooks();

$hooks->addCollector('collector-object', function (GreetingContext $context, string $name): array {
    return [$context->id, $name];
});

var_dump($hooks->collect('collector-object', new GreetingContext(100), 'Bar'));
```

When several callbacks need shared state, pass an explicit typed context object rather than relying on a global parameter bag.

## Callback Forms

The registration APIs accept:

- closures
- function names
- object method arrays such as `[$object, 'methodName']`
- class method arrays such as `['ClassName', 'methodName']`

If a class name and non-static method are provided, the class is instantiated and the method is called on that instance.

## Debugging

`debug()` and `setSourceFile()` remain available.

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->debug(function (string $message): void {
    echo $message . PHP_EOL;
});

$hooks->setSourceFile('/path/to/file.php');
$hooks->addAction('greeting', 'FooBar::log');
$hooks->doAction('greeting');
```

## Deferred to Later Milestones

The current branch does not yet implement:

- processors
- renderers
- resolvers
- framework-specific integrations

Until the renderer milestone exists, convert collected string results explicitly in userland, for example with `implode('', $hooks->collect('report'))`.

## Upgrading from 1.x

Version `2.0` removes the legacy `register()` / `all()` dispatch model entirely.

- Side-effect callbacks should move to `addAction()` / `doAction()`.
- Sequential value transformations should move to `addFilter()` / `applyFilters()`.
- Output aggregation should move to `addCollector()` / `collect()`.
- Global parameter arrays should move to explicit invocation arguments or typed context objects.
- String conversion should use `implode()` temporarily until renderers are added in a later milestone.

Example migration:

```php
// Version 1
$hooks->register('menu', $callback)->all('menu')->toArray();

// Version 2
$hooks->addCollector('menu', $callback);
$results = $hooks->collect('menu');
```

## Testing

```bash
composer test
composer analyse
composer format
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [.github/CONTRIBUTING.md](.github/CONTRIBUTING.md).

## Credits

- [Mohamed Magdi](https://github.com/magdicom)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
