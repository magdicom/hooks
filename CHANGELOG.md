# Changelog

## Unreleased

- raise the package minimum PHP version to 8.2 for the upcoming 2.0 line
- upgrade development tooling to Pest 3, PHP CS Fixer 3.95, and PHPStan 2
- add static analysis configuration and CI coverage
- isolate invocation results so nested and repeated dispatches do not share mutable output state
- make equal-priority registrations stable and return removable registration handles
- add listener inspection and removal APIs for querying, enumerating, and clearing registrations
- add framework-independent action, filter, and collector APIs with distinct execution semantics
- formalize the deprecated legacy compatibility layer for register/all/first/last/toArray/toString
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
