# Hooks — Extension points for PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)
[![Tests](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml/badge.svg?branch=2.0)](https://github.com/magdicom/hooks/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/magdicom/hooks.svg?style=flat-square)](https://packagist.org/packages/magdicom/hooks)

`magdicom/hooks` provides named extension points for PHP with actions, filters, collectors, processors, and renderers.

Hooks lets libraries and applications expose explicit places where outside code can participate without coupling the core package to a framework, container, or global runtime state.

Version `2.0` is an intentionally breaking release. If you are upgrading from `1.x` or an earlier `2.0` beta, read [UPGRADE.md](UPGRADE.md) before migrating code.

The core package is framework-independent. Laravel integration is available through [`magdicom/laravel-hooks`](https://github.com/magdicom/laravel-hooks), and complete documentation is available at [hooks.momagdi.com](https://hooks.momagdi.com).

Use the hook model that matches the extension point:

- actions run side effects
- filters transform values sequentially
- collectors gather independent contributions

Processors and renderers handle collector results when raw contributions need to be finalized into another value or string output.

The action and filter terminology is inspired by the WordPress hooks system. This package is independently implemented and is not affiliated with or endorsed by WordPress or the WordPress Foundation.

## Installation

This package currently targets PHP `8.2` or newer.

```bash
composer require magdicom/hooks:"^2.0@beta"
```

The explicit beta constraint is required while version 2 is a prerelease; the command will be simplified after stable `2.0.0` is published.

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
Each handle belongs only to the exact `Hooks` instance that created it.
Repeated callback-based or handle-based removals are deterministic: once the matching registration is gone, later removals return `false`.

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
use Magdicom\Resolvers\NativeResolver;

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
Custom processors and renderers can use narrower raw-result generics on their own implementations, such as `ResultProcessor<string, string>` or `Renderer<string>`.
The `Hooks` registry itself is still collector-wide and not endpoint-typed, so `setProcessor()` and `setRenderer()` are documented against broad `list<mixed>` collector results rather than narrow endpoint-specific result types.
If a project knows a given collector always produces strings, treating a narrower processor as compatible remains the developer's responsibility until a future typed-endpoint design exists.

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
- `processWith(string $hookName, ResultProcessor|callable|string $processor, mixed ...$arguments): mixed`

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

If no processor is configured for a collector endpoint, `process()` throws `Magdicom\Exceptions\MissingProcessorException`.
If a class-name processor resolves to an object that does not implement `ResultProcessor`, `process()` throws `Magdicom\Exceptions\InvalidProcessorException`.

`collect()` always bypasses processors and returns raw one-entry-per-callback results.
That raw bypass still applies when the endpoint currently has a renderer in the shared processor slot.
`process()` runs the configured collector processor and returns its output as-is.
Exact registration removal still goes through `RegistrationHandle::remove()`. Callback-based `removeAction()`, `removeFilter()`, and `removeCollector()` remain priority-aware callback removal APIs and do not accept registration handles.

Use persistent processing when a collector hook point has one normal interpretation:

```php
use Magdicom\Hooks;
use Magdicom\Processors\FirstNonNullProcessor;

$hooks = new Hooks();

$hooks->addCollector('customer.email_candidates', fn (Customer $customer): ?string => $customer->primaryEmail);
$hooks->addCollector('customer.email_candidates', fn (Customer $customer): ?string => $customer->billingEmail);

$hooks->setProcessor(
    'customer.email_candidates',
    FirstNonNullProcessor::class,
);

$email = $hooks->process(
    'customer.email_candidates',
    $customer,
);
```

Use one-off processing when the caller needs to select the output strategy. The supplied processor is used for that call only; it is not stored and does not replace any configured processor or renderer.

```php
use Magdicom\Processors\FirstNonNullProcessor;

$email = $hooks->processWith(
    'customer.email_candidates',
    FirstNonNullProcessor::class,
    $customer,
);
```

Because processors and renderers share one collector slot, callable registrations do not carry hidden renderer metadata:

- a callable assigned through `setProcessor()` can still be used by `render()` if it returns a string
- a callable assigned through `setProcessor()` causes `render()` to throw `Magdicom\Exceptions\InvalidRendererException` if it returns a non-string value
- a callable assigned through `setRenderer()` can still be used by `process()`
- `process()` returns callable output as-is and does not apply renderer-specific string validation
- `setProcessor()` and `setRenderer()` always replace whatever was previously stored for that collector endpoint

Interface-based instances and resolver-backed class names remain distinguishable: `process()` accepts resolved `ResultProcessor` implementations, while `render()` requires a resolved `Renderer`.

## Renderers and Built-ins

Renderers are specialized processors that guarantee string output and reuse the same single processor slot:

- `setRenderer(string $hookName, Renderer|callable|string $renderer): self`
- `render(string $hookName, mixed ...$arguments): string`
- `renderWith(string $hookName, Renderer|callable|string $renderer, mixed ...$arguments): string`

`render()` requires the configured processor to be a renderer. If no renderer is configured, it throws `Magdicom\Exceptions\MissingRendererException`.
If a collector endpoint is configured with a non-renderer processor, a class-name renderer resolves to the wrong type, or a callable renderer returns a non-string value, `render()` throws `Magdicom\Exceptions\InvalidRendererException`.

Callable renderers must accept `(array $results, ProcessingContext $context): string`.
If a string is callable in PHP, such as a named function or static method string, it is executed directly as a renderer. Non-callable strings are treated as class names and resolved through `Resolver`.

One-off rendering is useful when the caller needs a specific representation without changing the hook point's configured renderer:

```php
use Magdicom\Hooks;
use Magdicom\ProcessingContext;
use Magdicom\Renderer;

/** @implements Renderer<ReceiptSection> */
final class OrderReceiptRenderer implements Renderer
{
    public function process(array $results, ProcessingContext $context): string
    {
        return implode('', array_map(
            static fn (ReceiptSection $section): string => '<section>' . htmlspecialchars($section->html) . '</section>',
            $results,
        ));
    }
}

$hooks = new Hooks();

$hooks->addCollector('order.receipt.sections', fn (Order $order): ReceiptSection => $order->summarySection());
$hooks->addCollector('order.receipt.sections', fn (Order $order): ReceiptSection => $order->paymentSection());

$html = $hooks->renderWith(
    'order.receipt.sections',
    OrderReceiptRenderer::class,
    $order,
);
```

Built-ins currently shipped for collector endpoints:

- `Magdicom\Processors\BooleanAndProcessor`
- `Magdicom\Processors\BooleanOrProcessor`
- `Magdicom\Processors\ConcatenateRenderer`
- `Magdicom\Processors\FlattenProcessor`
- `Magdicom\Processors\MergeProcessor`
- `Magdicom\Processors\FirstProcessor`
- `Magdicom\Processors\FirstNonNullProcessor`
- `Magdicom\Processors\LastProcessor`

`ConcatenateRenderer` accepts an optional separator string. Each raw result is rendered individually using the existing string/scalar/Stringable/null rules, then the rendered entries are joined with that separator. `null` still occupies its original position as an empty rendered entry.
`FlattenProcessor` requires every top-level collector result to be an array, discards array keys, preserves callback and array iteration order, and returns a flattened list. Use depth `0` to concatenate only the top-level callback arrays, a positive depth to flatten that many nested levels, or `-1` for unlimited flattening.
`MergeProcessor` also requires array results, but unlike `FlattenProcessor` it keeps normal `array_merge()` semantics: later string keys replace earlier ones, numeric keys are appended and reindexed, and nested arrays are not recursively merged.
`BooleanAndProcessor` and `BooleanOrProcessor` require strictly boolean collected results. They do not cast with PHP truthiness. Empty results return `true` for AND and `false` for OR.
The built-ins validate those raw result shapes at runtime, while the untyped collector registry still leaves endpoint-to-processor compatibility as a developer responsibility.

```php
use Magdicom\Hooks;
use Magdicom\Processors\BooleanAndProcessor;
use Magdicom\ProcessingContext;
use Magdicom\Processors\ConcatenateRenderer;
use Magdicom\Processors\FlattenProcessor;
use Magdicom\Processors\FirstProcessor;
use Magdicom\Processors\MergeProcessor;

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

$hooks->setProcessor('navigation', new FlattenProcessor());
var_dump($hooks->process('navigation'));

$hooks->setProcessor('configuration', new MergeProcessor());
var_dump($hooks->process('configuration'));

$hooks->setProcessor('requirements', new BooleanAndProcessor());
var_dump($hooks->process('requirements'));

$hooks->setProcessor('report', new FirstProcessor());
var_dump($hooks->process('report'));
```

`render()` is a string-only convenience for collector endpoints that are configured with a renderer.
The examples in this section are covered by automated tests so the documented signatures and behaviors stay aligned with the shipped API.

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
