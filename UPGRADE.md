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
- If a caller needs to choose the output strategy per invocation, use `processWith()` or `renderWith()` instead of configuring the hook point persistently.

## Beta-to-Beta Namespace Changes

These namespace changes were made before stable 2.0 to establish a clean long-term package structure. They are not version-1 compatibility work, and no aliases or compatibility shims are provided.

| Before | After |
| --- | --- |
| `Magdicom\Processor\BooleanAndProcessor` | `Magdicom\Processors\BooleanAndProcessor` |
| `Magdicom\Processor\BooleanOrProcessor` | `Magdicom\Processors\BooleanOrProcessor` |
| `Magdicom\Processor\ConcatenateRenderer` | `Magdicom\Processors\ConcatenateRenderer` |
| `Magdicom\Processor\FirstNonNullProcessor` | `Magdicom\Processors\FirstNonNullProcessor` |
| `Magdicom\Processor\FirstProcessor` | `Magdicom\Processors\FirstProcessor` |
| `Magdicom\Processor\FlattenProcessor` | `Magdicom\Processors\FlattenProcessor` |
| `Magdicom\Processor\LastProcessor` | `Magdicom\Processors\LastProcessor` |
| `Magdicom\Processor\MergeProcessor` | `Magdicom\Processors\MergeProcessor` |
| `Magdicom\InvalidProcessorException` | `Magdicom\Exceptions\InvalidProcessorException` |
| `Magdicom\InvalidRendererException` | `Magdicom\Exceptions\InvalidRendererException` |
| `Magdicom\MissingProcessorException` | `Magdicom\Exceptions\MissingProcessorException` |
| `Magdicom\MissingRendererException` | `Magdicom\Exceptions\MissingRendererException` |
| `Magdicom\NativeResolver` | `Magdicom\Resolvers\NativeResolver` |

## Beta-to-Beta Debugging API Removal

The legacy mutable debugging/source-file API was removed before stable 2.0:

- `debug()`
- `setSourceFile()`
- `getSourceFile()`

These methods came from the version-1 runtime model and are not replaced by a core observer, logger, event dispatcher, endpoint-definition system, or debugging abstraction. Instrument hook registrations and invocations in application code when a project needs runtime tracing.

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
- `process()` throws `Magdicom\Exceptions\MissingProcessorException` when no collector processor is configured.
- `render()` throws `Magdicom\Exceptions\MissingRendererException` when no collector renderer is configured.
- `processWith()` and `renderWith()` use the supplied processor or renderer for one call only and do not read, write, replace, or clear persistent processor configuration.
- `setProcessor()` and `setRenderer()` replace the same collector processing slot.
- `render()` throws `Magdicom\Exceptions\InvalidRendererException` when the shared slot contains a non-renderer processor, a resolved class of the wrong type, or a callable that returns a non-string value.
- Equal-priority listeners keep registration order.
- Registrations added or removed during dispatch affect only later invocations.

## Optional Collector Processing

If a version-1 integration depended on collecting values and then converting them into a final shape, keep registration on collectors and make the processing step explicit in version 2.

```php
use Magdicom\Hooks;
use Magdicom\Processors\ConcatenateRenderer;

$hooks = new Hooks();

$hooks->addCollector('menu', fn (): string => '<li>Home</li>');
$hooks->addCollector('menu', fn (): string => '<li>Docs</li>');

$raw = $hooks->collect('menu');

$hooks->setRenderer('menu', new ConcatenateRenderer());
$html = $hooks->render('menu');
```

Class-name processors and renderers resolve through the framework-neutral `Resolver` abstraction. `new Hooks()` uses `Magdicom\Resolvers\NativeResolver` automatically, and a framework wrapper can swap in its own resolver implementation later.
Callable processors and renderers follow the invoked method contract of the shared slot: `process()` returns callable output as-is, while `render()` validates callable output as a string.
