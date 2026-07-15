# Alight Framework Development Guide

## Purpose

This repository contains the Alight framework. Changes here affect every
application and extension that depends on `juneszh/alight`, so treat public API,
configuration, and runtime behavior as compatibility contracts.

The PHP requirement and dependency constraints in `composer.json` are
authoritative. Alight-Project provides the reference application structure, and
Alight-Admin is a downstream extension.

Alight supports two Symfony Cache dependency lines:

- PHP 8.3 uses Symfony Cache 7.4 LTS.
- PHP 8.4.1 or newer may use Symfony Cache 8.x.

Changes to cache integration must pass both dependency combinations in CI.

## Source Map

- `src/App.php`: framework startup and project-root resolution.
- `src/Config.php`: configuration defaults and loading.
- `src/Route.php`, `src/RouteUtility.php`: route declaration API.
- `src/Router.php`: route import, caching, matching, and dispatch.
- `src/Request.php`, `src/Response.php`: HTTP input and output APIs.
- `src/Database.php`: Medoo initialization.
- `src/Cache.php`, `src/CacheHelper.php`: cache adapters and helpers.
- `src/ErrorHandler.php`, `src/Log.php`: error handling and logging.
- `src/Job.php`, `src/JobOption.php`: scheduler runtime and rules.
- `src/Utility.php`: general-purpose helpers.

All framework classes use the `Alight\` namespace through PSR-4 autoloading.

## Public Contracts

Unless a change is explicitly breaking, preserve:

- Public class names, method signatures, constants, and return behavior.
- Supported shapes and defaults in `Config::$default`.
- Route-file lifecycle and fluent `RouteUtility` behavior.
- Response status, headers, and JSON structure.
- Database and cache configuration shapes.
- Job scheduling semantics.

When a public contract changes, update in the same change:

- PHP types and PHPDoc.
- `README.md` examples and configuration reference.
- Alight-Project defaults when configuration is affected.
- Alight-Admin usage when the extension is affected.
- Dependency constraints and migration guidance for breaking changes.

## Coding Rules

- Use PHP 8.3 or newer and keep `declare(strict_types=1);` in source files.
- Follow the existing `Alight\` namespace and one-class-per-file layout.
- Use native types for constants, properties, parameters, and return values.
  Use `mixed` when a value is genuinely unconstrained instead of omitting its
  type.
- Use constructor property promotion and `readonly` for immutable dependencies
  or state.
- Fluent instance methods return `static`, side-effect-only methods return
  `void`, and methods that always terminate return `never`.
- Use PHPDoc for array shapes, generics, callback contracts, and behavior that
  native types cannot express; do not repeat obvious native types.
- Keep trailing commas in multiline arrays and argument lists.
- Document thrown exceptions and non-obvious side effects.
- Do not add application-specific policy to the framework.
- Do not edit installed copies under another project's `vendor` directory.
- Be careful with static state: routing, configuration, cache, and job classes
  retain process-level state and may be reused in long-running environments.
- Avoid shell-specific or destructive filesystem operations when a portable PHP
  implementation is practical.

## Security

- Treat request headers, query values, body data, hostnames, and paths as
  untrusted input.
- Do not weaken authorization, CORS, output escaping, or error redaction as an
  incidental change.
- Production error responses must not expose credentials, stack traces, or
  configuration secrets.
- Validate filesystem paths before reading, writing, or deleting runtime data.

## Documentation

`README.md` is the current user-facing API guide. Every example must use a real
method signature and a configuration shape accepted by the local source.
Complete PHP examples should pass `php -l`.

## Validation

Run the complete validation suite with:

```bash
composer check
```

Run PHPUnit, PHPStan, or PHP syntax checks separately with `composer test`,
`composer analyse`, and `composer lint`. Fix PHPStan findings at their source;
do not generate a baseline or add ignore comments merely to make the check
pass. Tests must reset modified static state in `tearDown()` or the next test
may inherit route, job, cache, or configuration state.

Also validate affected behavior in a local Alight-Project application.

## Definition of Done

- Changed source files pass PHP syntax validation.
- Unit tests cover new or changed behavior and the full suite passes.
- Public API and configuration changes are documented.
- Downstream impact on Alight-Admin and Alight-Project has been checked.
- No generated dependencies, runtime files, or unrelated local changes are
  included.
