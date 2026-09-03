# Upgrade to 2.0

`magdicom/hooks` 2.0 is intentionally breaking. The version-1 `register()` / `all()` API and its shared output model are removed.

## Core Migration

Choose the hook model that matches the callback behavior:

- Use actions for side effects.
- Use filters for sequential value transformation.
- Use collectors for raw result gathering.

## API Mapping

Version 1:

```php
$hooks->register('menu', $callback)->all('menu')->toArray();
```

Version 2:

```php
$hooks->addCollector('menu', $callback);
$results = $hooks->collect('menu');
```

## Migration Rules

- Side-effect callbacks migrate from `register()` to `addAction()` and `doAction()`.
- Sequential transformations migrate from `register()` to `addFilter()` and `applyFilters()`.
- Output aggregation migrates from `register()` / `all()` to `addCollector()` and `collect()`.
- Global parameter arrays migrate to explicit invocation arguments.
- Shared invocation context should be passed as a typed context object when several callbacks need the same state.
- If collected output needs post-processing, configure a collector processor and call `process()`.
- If collected output needs string rendering, configure a collector renderer and call `render()`.

## Signature Changes

Version 2 dispatch uses natural variadic arguments:

```php
$hooks->doAction(string $endpoint, mixed ...$arguments): void;
$hooks->applyFilters(string $endpoint, mixed $value, mixed ...$arguments): mixed;
$hooks->collect(string $endpoint, mixed ...$arguments): array;
```

Filter callbacks always receive the current filtered value first. Action and collector callbacks receive the dispatched arguments exactly as passed.

## Removed APIs

These version-1 APIs are no longer available in 2.0:

- `register()`
- `all()`
- `first()`
- `last()`
- `toArray()`
- `toString()`
- `__toString()`
- `setParameter()`
- `setParam()`
- `setParameters()`
- `setParams()`

## Behavior Changes

- Actions ignore callback return values.
- Filters return the original input when no listeners are registered.
- Collectors return `[]` when no listeners are registered.
- `collect()` always returns raw one-entry-per-callback results, even when a processor or renderer is configured.
- `process()` throws `MissingProcessorException` when no collector processor is configured.
- `render()` throws `MissingRendererException` when no collector renderer is configured.
- Equal-priority listeners keep registration order.
- Registrations added or removed during dispatch affect only later invocations.

## Optional Collector Processing

If a version-1 integration depended on collecting values and then converting them into a final shape, keep registration on collectors and make the processing step explicit in version 2.

```php
use Magdicom\Hooks;
use Magdicom\Processor\ConcatenateRenderer;

$hooks = new Hooks();

$hooks->addCollector('menu', fn (): string => '<li>Home</li>');
$hooks->addCollector('menu', fn (): string => '<li>Docs</li>');

$raw = $hooks->collect('menu');

$hooks->setRenderer('menu', new ConcatenateRenderer());
$html = $hooks->render('menu');
```

Class-name processors and renderers resolve through the framework-neutral `Resolver` abstraction. `new Hooks()` uses `NativeResolver` automatically, and a framework wrapper can swap in its own resolver implementation later.
