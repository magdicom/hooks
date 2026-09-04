# AGENTS.md

## Purpose

This repository contains `magdicom/hooks`, a lightweight framework-independent PHP hook package with:

- actions
- filters
- collectors
- collector processors
- collector renderers

The package should stay small, explicit, and free of framework coupling.

## Repository Layout

- `src/Hooks.php`: core runtime and public API surface.
- `src/RegistrationHandle.php`: registration removal handle.
- `tests/*.php`: Pest coverage for callbacks, ordering, inspection, and dispatch safety.
- `composer.json`: package metadata, scripts, and toolchain.
- `README.md`: user-facing API documentation.
- `UPGRADE.md`: version-1 to version-2 migration guide.
- `CHANGELOG.md`: release notes.

## Tech Stack

- PHP `^8.2`
- Pest
- PHPStan
- PHP CS Fixer

## Commands

Run from the repository root.

```bash
composer install
composer validate --strict
composer test
composer analyse
composer format
```

## Public API

Treat these methods on `Magdicom\Hooks` as the supported public surface unless the active 2.0 task explicitly changes them:

- `__construct`
- `addAction`
- `doAction`
- `addFilter`
- `applyFilters`
- `addCollector`
- `collect`
- `setProcessor`
- `hasProcessor`
- `processor`
- `clearProcessor`
- `process`
- `setRenderer`
- `render`
- `has`
- `hasAction`
- `hasFilter`
- `hasCollector`
- `count`
- `listeners`
- `actions`
- `filters`
- `collectors`
- `removeAction`
- `removeFilter`
- `removeCollector`
- `removeAll`
- `removeAllActions`
- `removeAllFilters`
- `removeAllCollectors`
- `debug`
- `setSourceFile`
- `getSourceFile`

Treat `Magdicom\RegistrationHandle`, `Magdicom\Resolver`, `Magdicom\NativeResolver`, `Magdicom\ProcessingContext`, `Magdicom\ResultProcessor`, `Magdicom\Renderer`, `Magdicom\MissingProcessorException`, `Magdicom\MissingRendererException`, `Magdicom\InvalidProcessorException`, `Magdicom\InvalidRendererException`, and the built-ins under `Magdicom\Processor\` as public as well.

Callback-specific `hasAction`, `hasFilter`, `hasCollector`, `removeAction`, `removeFilter`, and `removeCollector` are priority-aware. Exact registration removal should go through `RegistrationHandle::remove()`.

`Hooks` accepts an optional `Resolver` in its constructor. Non-static class callback registrations must resolve through that abstraction rather than direct instantiation.

Collector processing contracts are collector-only. `ProcessingContext` must stay minimal and immutable: hook point name plus original invocation arguments, without duplicating collected results or exposing the dispatcher.
`ResultProcessor` and `Renderer` PHPDoc generics are part of the public static-analysis contract and should stay accurate when adding new built-ins or examples.
`Hooks` must not pretend collector callback result types are statically linked to processor generic raw-result types. Narrower custom processor or renderer generics are fine on the implementations themselves, but `setProcessor()` and `setRenderer()` should stay documented against broad `list<mixed>` collector results until a future typed-endpoint design exists.
Processor registration is collector-only. `collect()` must remain raw, while `process()` uses the configured processor for that collector endpoint.
Renderer registration must reuse the same processor slot. `render()` requires a renderer and returns a string, while `collect()` continues to bypass that shared slot entirely.
String processors and renderers that PHP recognizes as callables, such as named functions and static method strings, must execute directly before non-callable strings are treated as resolver-backed class references.
Do not add hidden metadata just to remember whether a callable entered the shared slot through `setProcessor()` or `setRenderer()`. Callable behavior must follow the runtime contract of the method being invoked.
Callable renderers must be validated at runtime so non-string output fails with `InvalidRendererException` instead of an incidental `TypeError`.
`ConcatenateRenderer` must preserve its empty-string default behavior while allowing an explicit separator between individually rendered entries. `null` values must remain present as empty rendered positions when a separator is used.
`FlattenProcessor` must require array results at the top collector level, discard keys, preserve callback and array iteration order, and support depth `0`, positive depths, and `-1` unlimited flattening with clear standard-exception validation messages.
`MergeProcessor` must require array results at the top collector level and follow `array_merge()` semantics exactly: later string keys replace earlier ones, numeric keys append with reindexing, and nested arrays remain unmerged.
`BooleanAndProcessor` and `BooleanOrProcessor` must require strictly boolean collector results, use logical identity values for empty results, and never rely on PHP truthiness casts.
Keep explicit coverage for the distinction between callable strings and resolver-backed class strings for both processors and renderers.
Invalid class-based processor and renderer resolution should fail with explicit package exceptions, not generic argument errors.

## 2.0 Constraints

- Do not reintroduce version-1 compatibility APIs or output buffering.
- Keep the runtime framework-independent.
- Preserve deterministic listener ordering.
- Preserve listener snapshot behavior during dispatch.
- Preserve nested, recursive, and exception-safe execution.
- Prefer explicit APIs over generic magic behavior.

If a change conflicts with these rules, stop and surface the tradeoff.

## Implementation Guidance

- Keep changes concentrated in `src/Hooks.php` unless a small support type clearly improves the design.
- Avoid adding service containers, facades, observers, or framework-specific abstractions.
- Add comments only when the code would otherwise be genuinely hard to parse.
- Prefer straightforward control flow over abstraction for its own sake.

## Testing Expectations

Any behavior change should include Pest coverage for:

- happy path behavior
- ordering and priority behavior
- callback resolution
- dispatch mutation safety
- nested or exceptional execution when relevant

Before finishing work:

1. Run `composer test`.
2. Run `composer analyse`.
3. Run `composer validate --strict` when Composer metadata or scripts change.
4. Run `composer format` if PHP files changed.
5. Append manual testing instructions to the GitHub project task when they are practical.

## Documentation Expectations

- Keep `README.md` aligned with the shipped API.
- Keep `UPGRADE.md` aligned with the actual migration surface.
- Update `CHANGELOG.md` for user-visible changes.
- Add or maintain executable coverage for important README examples when the public processor or renderer API changes.
- When breaking APIs intentionally, document the migration path clearly instead of preserving shims.

## Planning Guidance

Good task slices in this repo:

- `Remove version-1 dispatch APIs from Hooks`
- `Switch hook invocation to variadic arguments`
- `Add type-aware action/filter/collector removal APIs`
- `Add resolver-backed class callback support`
- `Add collector processing contracts and immutable context`
- `Add collector processor registration and process()`
- `Add renderer convenience and minimal built-ins`
- `Document the version-2 migration path`

Weak task slices:

- `Refactor everything`
- `Improve architecture`
- `Cleanup codebase`

## What To Avoid

- Reintroducing removed version-1 APIs
- Framework coupling
- Ambiguous removal behavior across hook types
- Changing ordering semantics without tests
- Large rewrites without a bounded reason

## Board Workflow

- Move fully completed tasks to `In review`.
- Append manual testing instructions to the GitHub project task info when useful.
- Do not move tasks directly to `Done` unless explicitly requested.
