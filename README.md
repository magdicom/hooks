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

The action and filter terminology is inspired by the WordPress hooks system. This package is independently implemented and is not affiliated with or endorsed by WordPress or the WordPress Foundation.

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

$boot = fn (): null => null;
$handle = $hooks->addAction('boot', $boot, 10);

var_dump($hooks->has('boot'));
var_dump($hooks->hasAction('boot', $boot));
var_dump($hooks->count('boot'));
var_dump($hooks->listeners('boot'));
var_dump($hooks->actions('boot'));

$handle->remove();
```

Available inspection and removal methods:

- `has(string $hookName): bool`
- `hasAction(string $hookName, array|callable|null $callback = null, int $priority = 10): bool`
- `hasFilter(string $hookName, array|callable|null $callback = null, int $priority = 10): bool`
- `hasCollector(string $hookName, array|callable|null $callback = null, int $priority = 10): bool`
- `count(?string $hookName = null): int`
- `listeners(string $hookName): array`
- `actions(string $hookName): array`
- `filters(string $hookName): array`
- `collectors(string $hookName): array`
- `removeAction(string $hookName, array|callable $callback, int $priority = 10): bool`
- `removeFilter(string $hookName, array|callable $callback, int $priority = 10): bool`
- `removeCollector(string $hookName, array|callable $callback, int $priority = 10): bool`
- `removeAll(?string $hookName = null): int`
- `removeAllActions(?string $hookName = null): int`
- `removeAllFilters(?string $hookName = null): int`
- `removeAllCollectors(?string $hookName = null): int`

Callback-specific `has*()` and `remove*()` calls are priority-aware. If the same callback is registered at priorities `10` and `20`, querying or removing priority `10` will not affect priority `20`.

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

If a class name and non-static method are provided, the callback is resolved through the configured resolver and the method is called on that instance.

## Resolver

`Hooks` accepts an optional framework-neutral resolver. `new Hooks()` uses `NativeResolver` automatically.

```php
use Magdicom\Hooks;
use Magdicom\NativeResolver;

$hooks = new Hooks(new NativeResolver());
```

The resolver contract is:

- `Resolver::resolve(string $className): object`

This resolver is used for:

- non-static class callback registrations such as `[Listener::class, 'handle']`
- class-name collector processors
- class-name renderers

## Collector Processing Contracts

The public processing types now exist for collector endpoints:

- `ResultProcessor::process(array $results, ProcessingContext $context): mixed`
- `Renderer::process(array $results, ProcessingContext $context): string`
- `ProcessingContext`

For static analysis, `ResultProcessor` now documents generic raw-result and processed-result templates, and `Renderer` specializes that contract to a string result.

`ProcessingContext` contains only:

- the collector hook point name
- the original invocation arguments

Processors are collector-only. Actions remain side-effect hooks, and filters remain sequential transformation pipelines.

## Collector Processors

Collector endpoints can now keep raw `collect()` access while also exposing processed results through an endpoint-specific processor.

- `setProcessor(string $hookName, ResultProcessor|callable|string $processor): self`
- `hasProcessor(string $hookName): bool`
- `processor(string $hookName): ResultProcessor|callable|string|null`
- `clearProcessor(string $hookName): bool`
- `process(string $hookName, mixed ...$arguments): mixed`

`processor()` returns the configured processor reference exactly as stored:

- a processor instance if one was assigned
- the callable if a callable processor was assigned
- the class name string if a class-based processor was assigned

Callable processors must accept `(array $results, ProcessingContext $context): mixed`.
If a string is callable in PHP, such as a named function or static method string, it is executed directly as a processor. Non-callable strings are treated as class names and resolved through `Resolver`.

Class-name processors resolve through the configured `Resolver` and must implement `ResultProcessor`.

```php
use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\ResultProcessor;

$hooks = new Hooks();

$hooks->addCollector('report', fn (): string => 'first');
$hooks->addCollector('report', fn (): string => 'second');

$hooks->setProcessor('report', static function (array $results, ProcessingContext $context): string {
    return implode(', ', $results);
});

var_dump($hooks->collect('report'));
echo $hooks->process('report');
```

If no processor is configured for a collector endpoint, `process()` throws `MissingProcessorException`.
If a class-name processor resolves to an object that does not implement `ResultProcessor`, `process()` throws `InvalidProcessorException`.

`collect()` always bypasses processors and returns raw one-entry-per-callback results.
`process()` runs the configured collector processor and returns its output as-is.
Exact registration removal still goes through `RegistrationHandle::remove()`. Callback-based `removeAction()`, `removeFilter()`, and `removeCollector()` remain priority-aware callback removal APIs and do not accept registration handles.

## Renderers and Built-ins

Renderers are specialized processors that guarantee string output and reuse the same single processor slot:

- `setRenderer(string $hookName, Renderer|callable|string $renderer): self`
- `render(string $hookName, mixed ...$arguments): string`

`render()` requires the configured processor to be a renderer. If no renderer is configured, it throws `MissingRendererException`.
If a collector endpoint is configured with a non-renderer processor, a class-name renderer resolves to the wrong type, or a callable renderer returns a non-string value, `render()` throws `InvalidRendererException`.

Callable renderers must accept `(array $results, ProcessingContext $context): string`.
If a string is callable in PHP, such as a named function or static method string, it is executed directly as a renderer. Non-callable strings are treated as class names and resolved through `Resolver`.

Built-ins currently shipped for collector endpoints:

- `Magdicom\Processor\ConcatenateRenderer`
- `Magdicom\Processor\FirstProcessor`
- `Magdicom\Processor\FirstNonNullProcessor`
- `Magdicom\Processor\LastProcessor`

`ConcatenateRenderer` accepts an optional separator string. Each raw result is rendered individually using the existing string/scalar/Stringable/null rules, then the rendered entries are joined with that separator. `null` still occupies its original position as an empty rendered entry.

```php
use Magdicom\Hooks;
use Magdicom\Processor\ConcatenateRenderer;
use Magdicom\Processor\FirstProcessor;

$hooks = new Hooks();

$hooks->addCollector('report', fn (): string => 'first');
$hooks->addCollector('report', fn (): string => 'second');

$hooks->setRenderer('report', new ConcatenateRenderer());
echo $hooks->render('report');

$hooks->setRenderer('report', new ConcatenateRenderer(' | '));
echo $hooks->render('report');

$hooks->setRenderer('report', static function (array $results, ProcessingContext $context): string {
    return implode(', ', $results);
});
echo $hooks->render('report');

$hooks->setProcessor('report', new FirstProcessor());
var_dump($hooks->process('report'));
```

`render()` is a string-only convenience for collector endpoints that are configured with a renderer.

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

- flattening, merge, and boolean processors
- framework-specific integrations

## Upgrading from 1.x

Version `2.0` removes the legacy `register()` / `all()` dispatch model entirely.

- Side-effect callbacks should move to `addAction()` / `doAction()`.
- Sequential value transformations should move to `addFilter()` / `applyFilters()`.
- Output aggregation should move to `addCollector()` / `collect()`.
- Global parameter arrays should move to explicit invocation arguments or typed context objects.
- Optional collector post-processing should move to `setProcessor()` / `process()`.
- Optional collector string rendering should move to `setRenderer()` / `render()`.

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
