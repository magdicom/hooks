# PHP Hooks

[![Latest Version on Packagist](https://img.shields.io/packagist/v/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)
[![Tests](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml/badge.svg?branch=main)](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)

`magdicom/hooks` is a lightweight, framework-independent hook system for PHP.

The upcoming `2.0` line separates hook execution into three explicit models:

- actions for side effects
- filters for sequential value transformation
- collectors for independent result gathering

The legacy `register()` / `all()` API remains available as a deprecated compatibility layer for existing integrations.

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

$hooks->addAction('boot', function (array $vars) use (&$events): void {
    $events[] = 'prepare:' . $vars['name'];
}, 10);

$hooks->addAction('boot', function (array $vars) use (&$events): void {
    $events[] = 'finish:' . $vars['name'];
}, 20);

$hooks->doAction('boot', ['name' => 'hooks']);

var_dump($events);
```

### Filters

```php
use Magdicom\Hooks;

$hooks = new Hooks(['suffix' => '!']);

$hooks->addFilter('title', fn (string $value): string => trim($value), 10);
$hooks->addFilter('title', fn (string $value, array $vars): string => $value . $vars['suffix'], 20);
$hooks->addFilter('title', fn (string $value): string => strtoupper($value), 30);

echo $hooks->applyFilters('title', ' hello ');
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

## Execution Model

### Actions

- Register with `addAction()`
- Execute with `doAction()`
- Callback return values are ignored

### Filters

- Register with `addFilter()`
- Execute with `applyFilters()`
- Each callback receives the current value and must return the next value

### Collectors

- Register with `addCollector()`
- Execute with `collect()`
- Each callback runs independently and its raw return value is collected without implicit flattening

## Registration and Removal

All registration APIs return a `RegistrationHandle`.

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$handle = $hooks->addAction('boot', fn (): null => null, 10);

var_dump($hooks->has('boot'));
var_dump($hooks->has('boot', $handle));
var_dump($hooks->count('boot'));
var_dump($hooks->listeners('boot'));

$handle->remove();
```

Available inspection and removal methods:

- `has(string $hookName, RegistrationHandle|array|callable|null $listener = null): bool`
- `count(?string $hookName = null): int`
- `listeners(string $hookName): array`
- `remove(string $hookName, RegistrationHandle|array|callable $listener): bool`
- `removeAll(?string $hookName = null): int`

## Ordering and Dispatch Safety

- Lower numeric priorities run before higher numeric priorities.
- When priorities are equal, listeners keep their registration order.
- Listener additions or removals during dispatch affect the next invocation, not the current one.
- Nested and recursive executions are isolated from each other.
- Execution state is restored even if a callback throws.

## Parameters

Global parameters can be set with `setParameter()` or `setParameters()`. They are available to all hook executions.

Scoped parameters can be passed at invocation time:

- If the scoped value is an array, it is merged with global parameters.
- If the scoped value is an object, the object is passed first and global parameters are passed separately.

### Array Parameters

```php
use Magdicom\Hooks;

$hooks = new Hooks(['prefix' => 'Hello']);

$hooks->addAction('greet', function (array $vars): void {
    echo $vars['prefix'] . ' ' . $vars['name'];
});

$hooks->doAction('greet', ['name' => 'world']);
```

### Object Parameters

```php
use Magdicom\Hooks;

class GreetingContext
{
    public function __construct(public int $id)
    {
    }
}

$hooks = new Hooks(['name' => 'Bar']);

$hooks->register('legacy-object', function (GreetingContext $context, array $globals): array {
    return [$context->id, $globals['name']];
});

var_dump($hooks->all('legacy-object', new GreetingContext(100))->toArray());
```

For filters with object parameters, the current value is still passed first, the object is second, and global parameters are third.

## Callback Forms

The registration APIs accept any callback shape supported by the current implementation:

- closures
- function names
- object method arrays such as `[$object, 'methodName']`
- class method arrays such as `['ClassName', 'methodName']`

If a class name and non-static method are provided, the class is instantiated and the method is called on that instance.

## Legacy Compatibility Layer

The following methods remain available for legacy callers and are deprecated for new code:

- `register()`
- `all()`
- `first()`
- `last()`
- `toArray()`
- `toString()`
- `__toString()`

Legacy behavior notes:

- `all()`, `first()`, and `last()` populate a legacy result object for later `toArray()` / `toString()` access.
- The legacy result is isolated per invocation and safe for nested execution.
- Running actions, filters, or collectors does not populate the legacy output buffer.

### Legacy Example

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->register('legacy-greeting', fn (): string => 'Hello', 10)
    ->register('legacy-greeting', fn (): string => 'World', 20);

echo $hooks->all('legacy-greeting')->toString(' ');
```

## Debugging

`debug()` and `setSourceFile()` remain available.

```php
use Magdicom\Hooks;

$hooks = new Hooks();

$hooks->debug(function (string $message): void {
    echo $message . PHP_EOL;
});

$hooks->setSourceFile('/path/to/file.php');
$hooks->register('greeting', 'FooBar::log');
$hooks->all('greeting');
```

## Deferred to the Renderer Milestone

This milestone only establishes the execution foundation.

The following topics are intentionally postponed:

- renderers
- result processors
- formatting pipelines on top of collector output
- framework-specific integrations, including Laravel container features, facades, Artisan commands, and Blade helpers

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
