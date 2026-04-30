# Tactical Plan: PHP Compatibility and Quality Gates

## Purpose

This tactical plan covers the nearest engineering cycle for Adaptive Entity
Manager. It turns the broader open source maturity roadmap into a small,
practical set of changes that can be completed before larger architecture work
such as domain-agnostic Value Object mapping or DDD aggregate mapping.

The main goal is to make the package safer to install, easier to trust, and
ready for current PHP versions.

## Timeframe

Target: 1-2 weeks.

This should be treated as a short stabilization cycle, not as a large
refactoring effort.

## Scope

- Verify PHP 8.1-8.4 compatibility.
- Remove known PHP 8.4 deprecation risks.
- Add automated test execution in CI.
- Introduce a basic static analysis gate.
- Keep Composer scripts useful for local development and CI.
- Add minimal documentation updates for supported PHP versions and quality
  checks.

## Out of Scope

- Domain-agnostic Value Object mapping implementation.
- DDD aggregate mapping implementation.
- Public API redesign.
- Large refactoring of metadata, persisters, repositories, or proxies.
- Symfony bundle or OpenTelemetry bridge changes.

## Current Observations

The package currently requires PHP `>=8.1` in `composer.json`, which means it is
installable on future PHP versions unless dependency resolution prevents it.

This is convenient, but it should be backed by CI. Without a version matrix, the
package can appear compatible with PHP 8.4 or later even if tests have not been
run there.

Known PHP 8.4 readiness concern:

- Several parameters use implicit nullable syntax, for example
  `SomeClass $value = null` instead of `?SomeClass $value = null`.

Files that should be checked first:

- `src/AdaptiveEntityManager.php`
- `src/PersistentCollection.php`
- `src/Exception/CommitFailedException.php`

## Work Plan

### 1. PHP 8.4 Readiness

Replace implicit nullable parameters with explicit nullable types.

Examples:

```php
ClassMetadataFactory $metadataFactory = null
```

should become:

```php
?ClassMetadataFactory $metadataFactory = null
```

And:

```php
Closure $callback = null
```

should become:

```php
?Closure $callback = null
```

Expected files:

- `src/AdaptiveEntityManager.php`
- `src/PersistentCollection.php`
- `src/Exception/CommitFailedException.php`

Acceptance criteria:

- No implicit nullable parameter deprecations on PHP 8.4.
- Existing tests continue to pass.
- No runtime behavior changes.

### 2. GitHub Actions Test Matrix

Add CI for the supported PHP versions.

Recommended matrix:

```yaml
php-version:
  - '8.1'
  - '8.2'
  - '8.3'
  - '8.4'
```

The workflow should run on:

- Pull requests.
- Pushes to `main`.

Initial CI command:

```bash
composer install --prefer-dist --no-progress
composer test
```

Acceptance criteria:

- PHPUnit runs automatically for PHP 8.1, 8.2, 8.3, and 8.4.
- CI status is visible on pull requests.
- `main` is protected by at least the test job, if branch protection is used.

### 3. Composer Script Review

Review Composer scripts and keep them convenient for local development and CI.

Current useful scripts:

- `composer test`
- `composer test:quick`
- `composer test:unit`
- `composer test:integration`

Potential improvement:

- Keep `composer test` as the stable CI entry point.
- Add a future `composer analyse` script after PHPStan is introduced.
- Add a future `composer check` script that runs tests and static analysis.

Note: `test:syntax` currently uses `find`, which is fine on Linux CI but less
portable for Windows users. This does not need to block the first CI step.

Acceptance criteria:

- CI uses stable Composer scripts.
- Local commands are documented or obvious from `composer.json`.
- No unnecessary script churn.

### 4. Static Analysis Baseline

Introduce PHPStan as the first static analysis tool.

Recommended starting point:

```bash
composer require --dev phpstan/phpstan
```

Recommended initial level:

```neon
parameters:
    level: 5
    paths:
        - src
        - tests
```

Start at a level that can be made green without large refactoring. Increase the
level later after the public API and metadata layer are more stable.

Acceptance criteria:

- PHPStan is configured.
- A Composer script exists for static analysis.
- CI runs static analysis.
- The starting level is documented and intentionally chosen.

### 5. Minimal Documentation Update

Update public documentation after CI and PHP compatibility are confirmed.

Recommended documentation changes:

- Mention supported PHP versions in `README.md`.
- Add CI badge after GitHub Actions exists.
- Mention that tests run on PHP 8.1-8.4.
- Optionally link this tactical plan from the strategic roadmap.

Acceptance criteria:

- README does not overclaim unsupported versions.
- Supported PHP versions match the CI matrix.
- Documentation reflects the current quality gate.

## Suggested Branches

Keep changes small and easy to review.

Suggested branch split:

```text
compat/php-84-readiness
ci/phpunit-matrix
qa/phpstan-baseline
docs/php-compat-quality-gates
```

If working alone, the first two items can be combined into one branch:

```text
ci/php-84-compatibility
```

Avoid a long-lived branch for the full maturity roadmap. This tactical plan
should be completed through small branches that can be merged quickly.

## Suggested Commit Messages

```text
fix: make nullable parameters explicit for PHP 8.4
ci: run PHPUnit across supported PHP versions
qa: add PHPStan baseline
docs: document PHP compatibility quality gates
```

## Done Criteria

This tactical cycle is complete when:

- Tests pass locally on the current development PHP version.
- CI passes on PHP 8.1, 8.2, 8.3, and 8.4.
- Known PHP 8.4 nullable parameter deprecations are removed.
- PHPStan runs in CI at the agreed starting level.
- README or roadmap documentation reflects the supported PHP versions.
- No domain model, VO mapping, or aggregate mapping redesign is mixed into this
  cycle.

## Follow-Up Work

After this tactical plan is complete, the next cycle can safely move to one of
the strategic roadmap items:

- Domain-agnostic Value Object mapping.
- Public/internal API stabilization.
- English documentation pass.
- End-to-end examples for adapters and DDD aggregates.
