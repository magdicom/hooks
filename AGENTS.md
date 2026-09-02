# AGENTS.md

## Purpose

This repository contains `magdicom/hooks`, a lightweight PHP action hooks package.

The package is intentionally small. Changes should preserve that property unless there is a clear product-level reason to expand scope.

## Repository Layout

- `src/Hooks.php`: the package implementation and the public API surface.
- `tests/*.php`: Pest test coverage for string output, array output, callbacks, and parameters.
- `composer.json`: package metadata, autoloading, and local scripts.
- `README.md`: user-facing behavior and examples. Keep this aligned with shipped behavior.
- `CHANGELOG.md`: release notes. Update for user-visible changes.

## Tech Stack

- PHP `^8.0`
- Pest for tests
- PHP CS Fixer for formatting

## Commands

Run from the repository root.

```bash
composer install
composer test
composer test-coverage
composer format
```

If dependencies are missing, install them before making behavioral changes or running tests.

## Working Rules

### 1. Protect the public API

This package is consumed as a library. Treat all public methods on `Magdicom\Hooks` as part of the supported API unless the user explicitly asks for a breaking change.

Current public methods include:

- `__construct`
- `register`
- `all`
- `first`
- `last`
- `toArray`
- `toString`
- `__toString`
- `setParameter`
- `setParam`
- `setParameters`
- `setParams`
- `debug`
- `setSourceFile`
- `getSourceFile`

When modifying behavior:

- Prefer additive changes over breaking changes.
- Preserve existing argument order and return types where possible.
- Keep method chaining intact for fluent methods that currently return `self`.
- When introducing a replacement API, provide a compatibility path and mark legacy entry points clearly in code and docs.

### 1.1. 2.0 migration constraints

For the framework-independent `2.0` milestone, prefer these architectural rules:

- eliminate shared mutable execution output
- use isolated per-invocation result state
- support nested and recursive dispatch safely
- make listener mutation during dispatch affect only future invocations
- preserve legacy APIs through a compatibility layer where reasonable
- do not add Laravel or container-specific behavior

If a proposed change conflicts with these rules, stop and surface the tradeoff explicitly.

### 2. Require tests for behavior changes

Any functional change must include or update Pest tests.

Expected cases:

- happy path behavior
- ordering and priority behavior
- callback resolution behavior
- parameter merging behavior
- regression coverage for the specific bug or edge case

If a change affects output format or documented examples, update `README.md` in the same change.

### 3. Keep implementation small and explicit

Avoid introducing unnecessary abstraction layers, service containers, traits, or framework-specific dependencies.

Good changes in this repo usually look like:

- small targeted fixes in `src/Hooks.php`
- small supporting value objects when isolated execution state requires them
- clear tests proving the behavior
- documentation updates when the external contract changes

### 4. Favor deterministic behavior

Hook execution order, callback preparation, and output aggregation are core behavior. Any change that could alter ordering or callback invocation semantics needs explicit tests.

Be especially careful around:

- priority sorting
- equal-priority registration order stability
- handling callable vs array callback definitions
- registration handle lifecycle and listener removal semantics
- object parameter passing
- array merge semantics in output and parameters
- repeated execution and output reset behavior
- restoring execution state when callbacks throw

### 5. Respect backward compatibility in docs and examples

Examples in `README.md` should remain copy-pasteable.

When updating examples:

- use valid PHP syntax
- keep examples minimal
- prefer documented behavior that is covered by tests

## Code Style

- Use `declare(strict_types=1);` in PHP source files.
- Prefer typed properties and typed method signatures.
- Match the existing namespace layout unless there is a strong reason to restructure.
- Keep comments sparse and useful.
- Prefer straightforward control flow over cleverness.

## Testing Expectations

Before finishing work:

1. Run `composer test` when dependencies are available.
2. Run `composer format` if PHP files changed.
3. Run static analysis if the toolchain includes it for the current milestone.
4. Verify that changed behavior is covered by Pest tests.
5. Add manual testing instructions when they are practical for the task, especially for user-visible behavior, tooling changes, CI workflow changes, or integration-adjacent changes that automated tests do not fully prove.

If tests cannot be run, state that clearly in the final handoff.

## Change Checklist

For behavioral changes:

- update implementation
- add or update tests
- update `README.md` if user-facing behavior changed
- update `CHANGELOG.md` for notable shipped changes
- document deprecations when legacy APIs are preserved temporarily

For maintenance-only changes:

- avoid unnecessary README churn
- do not rewrite stable code without a concrete benefit

## Planning Guidance

When breaking work into tasks for this repository:

- keep each task independently testable
- separate refactors from behavior changes
- separate API additions from documentation cleanup
- prefer vertical slices that end in passing tests

Good task titles:

- `Fix object callback validation in register()`
- `Add regression tests for scoped parameter override behavior`
- `Document debug workflow and source file usage`
- `Refine output aggregation for keyed array responses`
- `Introduce isolated invocation results for 2.0 dispatch`
- `Add stable registration handles and listener inspection APIs`

Weak task titles:

- `Improve codebase`
- `Refactor hooks`
- `Cleanup everything`

## What To Avoid

- Introducing framework coupling
- Making undocumented breaking API changes
- Changing hook ordering behavior without tests
- Editing tests to fit incorrect behavior unless the intended contract has changed
- Large rewrites without a measured reason

## Definition of Done

A task is done when:

- the code change is implemented
- relevant tests exist and pass, or inability to run them is explicitly reported
- manual testing instructions are included when they are reasonably possible and add value
- docs are updated if the public contract changed
- the change remains consistent with the package’s lightweight scope
- deprecations and compatibility notes are explicit when legacy APIs are touched

Completed tasks should be moved to `In review` on the GitHub project board, not directly to `Done`.
When manual testing instructions are relevant, append them to the GitHub project task info for that item instead of repeating them in the final chat response unless the user explicitly asks for them there.
