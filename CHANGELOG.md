# Changelog

## Unreleased

Version 2.0 is intentionally breaking and does not preserve version-1 dispatch or output APIs.

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
- make listener mutations during dispatch apply only to the next invocation via explicit snapshots
- add regression coverage for nested, recursive, and exception-safe execution cleanup
- expand automated coverage for priority ordering and repeated independent invocations across the new APIs
- document the 2.0 execution foundation and explicitly defer renderer/result-processor work to a later milestone

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
