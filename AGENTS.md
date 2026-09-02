# AGENTS.md

## Purpose

This repository contains `magdicom/hooks`, a lightweight PHP hook package focused on three framework-independent execution models:

- actions
- filters
- collectors

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

Treat `Magdicom\RegistrationHandle` as public as well.

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
3. Run `composer format` if PHP files changed.
4. Append manual testing instructions to the GitHub project task when they are practical.

## Documentation Expectations

- Keep `README.md` aligned with the shipped API.
- Keep `UPGRADE.md` aligned with the actual migration surface.
- Update `CHANGELOG.md` for user-visible changes.
- When breaking APIs intentionally, document the migration path clearly instead of preserving shims.

## Planning Guidance

Good task slices in this repo:

- `Remove version-1 dispatch APIs from Hooks`
- `Switch hook invocation to variadic arguments`
- `Add type-aware action/filter/collector removal APIs`
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
