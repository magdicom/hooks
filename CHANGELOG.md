# Changelog

## Unreleased

## v2.0.0-beta.1 - 2026-09-07

Version 2.0 is intentionally breaking and does not preserve version-1 dispatch, global parameter, or output APIs.

- raise the package minimum PHP version to 8.2 for the upcoming 2.0 line
- upgrade development tooling to Pest 3, PHP CS Fixer 3.95, and PHPStan 2
- add static analysis configuration and CI coverage
- isolate invocation results so nested and repeated dispatches do not share mutable output state
- make equal-priority registrations stable and return removable registration handles
- add listener inspection and removal APIs for querying, enumerating, and clearing registrations
- add framework-independent action, filter, and collector APIs with distinct execution semantics
- remove the version-1 legacy registry and dispatch/output APIs from the 2.0 branch
- switch action, filter, and collector invocation to explicit variadic arguments and remove the global parameter bag
- make registration handles instance-owned, remove magic handle forwarding, and change the default priority to 10
- add type-aware action/filter/collector inspection and removal APIs to avoid ambiguous callback matching
- make callback-specific action, filter, and collector lookup/removal priority-aware while keeping exact handle removal on registrations
- add framework-neutral `Resolver` and `NativeResolver` support for non-static class callbacks, class-name processors, and class-name renderers without a container dependency
- add public collector processing contracts via `ResultProcessor`, `Renderer`, and immutable `ProcessingContext`
- add collector-only processor registration and processed dispatch APIs while keeping `collect()` as raw access
- add renderer-specific collector dispatch plus built-in concatenate, first, first-non-null, and last processors
- harden collector processing with explicit invalid processor/renderer exceptions and nested collect/process/render regression coverage
- add callable renderer support and execute PHP-callable processor/renderer strings before resolver-backed class-name resolution
- add configurable separator support to `ConcatenateRenderer` while preserving empty-string defaults and null slot positions
- add PHPStan generics to `ResultProcessor` and `Renderer`, with matching annotations on built-in processors and test doubles
- add explicit regression coverage and documentation for callable-string versus resolver-backed class-string processor and renderer resolution
- clarify shared-slot callable processor and renderer behavior, including explicit runtime validation for callable renderer output
- add automated coverage for key README processor and renderer examples
- validate the public processor and renderer generics from a consumer PHPStan fixture and document that collector-result compatibility is not yet type-linked by the registry
- add built-in `FlattenProcessor` support for depth-aware list flattening of array-based collector results
- add built-in `MergeProcessor` support with standard PHP `array_merge()` semantics for array-based collector results
- add built-in strict `BooleanAndProcessor` and `BooleanOrProcessor` support for boolean collector requirements
- add explicit regression coverage for `collect()` bypassing configured renderers and for `process()` using a configured renderer through the shared slot
- clarify that built-in processors and renderers validate their accepted raw result shapes at runtime while the shared collector registry remains broadly typed
- improve package metadata and discoverability with version-2 positioning, richer Composer keywords, and support links
- make listener mutations during dispatch apply only to the next invocation via explicit snapshots
- add regression coverage for nested, recursive, and exception-safe execution cleanup
- expand automated coverage for priority ordering and repeated independent invocations across the new APIs
- document the collector-only processor and renderer model, including migration guidance for `collect()`, `process()`, and `render()`
- harden GitHub Actions by replacing formatting auto-commits with check-only validation, expanding the PHP matrix, adding strict Composer validation, and removing the post-release changelog auto-commit workflow

## v1.0.5 - 2022-01-12

Added debug functionality

## v1.0.4 - 2022-01-02

- accept an [object](https://github.com/magdicom/hooks#parameters) as action hook parameter

## v1.0.3 - 2021-12-31

- improve output handling

## v1.0.2 - 2021-12-31

- improved output handling

## v1.0.0 - 2021-12-29

- initial release
