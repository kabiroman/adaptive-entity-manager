---
name: project-maturity-roadmap
overview: "Grow Adaptive Entity Manager from an early public library into a more mature OSS package: more stable API, stronger documentation, CI, static analysis, examples, and cleaner DDD-oriented design."
todos:
  - id: docs-positioning
    content: Refresh positioning, README, and English-first documentation.
    status: pending
  - id: quality-gates
    content: Add CI, a PHPUnit version matrix, static analysis, and code style checks.
    status: pending
  - id: api-stability
    content: Define public vs internal API, CHANGELOG discipline, and a simple release policy.
    status: pending
  - id: vo-mapping
    content: Design and ship a domain-agnostic conversion layer for Value Objects.
    status: pending
  - id: ddd-examples
    content: Add DDD aggregate mapping guidance and end-to-end examples.
    status: pending
  - id: ecosystem-sync
    content: Align core releases with the Symfony bundle and OpenTelemetry bridge.
    status: pending
isProject: false
---

# AEM open source maturity roadmap

## Goal

Reach a state where the project is easy to recommend to external PHP developers: clear positioning, English-first documentation, measurable quality gates, a more stable public surface, and compelling usage examples.

## Current baseline

The project already has solid foundations: Composer packaging, a public namespace, README, tests, and a metadata / entity manager / unit of work / data adapter architecture. Its core value is **adaptive mapping** for legacy and mixed data sources—not replacing Doctrine ORM outright.

Key entry points:

- [composer.json](../../composer.json) — package metadata, dependencies, and Composer scripts.
- [src/AdaptiveEntityManager.php](../../src/AdaptiveEntityManager.php) — primary public API.
- [src/UnitOfWork.php](../../src/UnitOfWork.php) — commit and transaction lifecycle.
- [src/EntityPersister.php](../../src/EntityPersister.php) — persistence flow and dirty tracking.
- [RFC-003](../rfc/RFC-003-domain-agnostic-value-object-mapping.md) — direction for domain-clean Value Object mapping.

## Phase 1: Packaging and trust

Improve outward presentation without changing runtime behavior yet.

- Make English the canonical documentation language for user-facing material.
- Refresh README: purpose, non-goals, install steps, minimal example, adapter sample, known limitations.
- Add badges after CI exists: tests, PHP version, license, Packagist.
- State project status explicitly: experimental, beta, stable API, migration-oriented, etc.
- Structure docs into user guides, architecture references, and RFC/ADR notes.

**Outcome:** newcomers understand why AEM exists, where it fits, and what it deliberately does not solve.

## Phase 2: Automated quality

Ensure baseline quality is checked automatically.

- GitHub Actions running PHPUnit on supported PHP versions.
- PHPStan or Psalm starting at a pragmatic level.
- PHP-CS-Fixer or ECS for consistent formatting.
- Revisit Composer scripts that rely on shell tools if they hurt cross-platform usage.
- Minimum gate: tests + static analysis + code style.

**Outcome:** the package feels like maintained OSS, not only a promising concept.

## Phase 3: Public API stabilization

Before heavy promotion, clarify which surfaces are contractual.

- Document public vs internal types.
- Review `EntityManagerInterface`, `PersisterInterface`, `EntityDataAdapter`, `ClassMetadata`.
- Decide semver rules: what may change in minor vs major releases.
- Maintain CHANGELOG + a short release policy.
- Promote accepted RFCs into ADRs.

**Outcome:** consumers know which types they can safely depend on.

## Phase 4: Domain-agnostic Value Object mapping

The largest architectural step toward DDD-friendly usage.

- Do not pretend `Stringable` alone replaces a conversion story.
- Introduce a conversion layer that keeps domain classes free of AEM-specific contracts.
- Keep `ValueObjectInterface` for backward compatibility.
- Support converters via metadata options such as `class`, `converter`, `from`, `to`, `format`.
- Ship built-in handling for `Stringable`, `BackedEnum`, `JsonSerializable`, `DateTimeInterface`.
- Update [VALUE_OBJECTS.md](../VALUE_OBJECTS.md) with examples of clean domain VOs.

**Outcome:** domain code is not forced to implement `Kabiroman\AEM\ValueObject\ValueObjectInterface`.

## Phase 5: DDD aggregate mapping

After VO mapping lands, position AEM as an aggregate-oriented mapper.

- Document repositories targeting aggregate roots rather than every internal entity.
- Add aggregate metadata only if the API truly needs it.
- Explore reconstitution via named constructors or factories.
- Constrain or clearly document lazy loading inside aggregate boundaries.
- Provide an `Order`-style example covering `OrderId`, `Email`, `OrderStatus`, adapters, and repositories.

**Outcome:** AEM’s niche becomes “mapper for legacy/mixed sources with DDD-friendly boundaries,” not “generic ORM.”

## Phase 6: Examples and scenarios

Great libraries need fast on-ramps.

- `examples/legacy-db` with a minimal adapter.
- `examples/rest-api-adapter`.
- `examples/ddd-aggregate`.
- Short integration or smoke tests for examples when feasible.
- Link those scenarios from README.

**Outcome:** a motivated developer can see value within ten to fifteen minutes.

## Phase 7: Observability and ecosystem

After the core stabilizes, tighten the wider story.

- Keep the Symfony bundle aligned with core semver expectations.
- Confirm the OpenTelemetry bridge covers key lifecycle operations.
- Document extension points: adapters, converters, metadata providers, repositories.
- Publish a migration guide for legacy VO-interface users.

**Outcome:** AEM reads as a small ecosystem, not a lone package.

## Recommended sequencing

1. Documentation + CI first (high trust, low risk).
2. Static analysis + coding standards next (safer refactors afterward).
3. Implement the VO RFC as a real conversion layer.
4. Layer in DDD aggregate examples and optional reconstitution APIs.
5. Finally synchronize the Symfony bundle and observability tooling.

## Definition of “good maturity”

- README and primary docs are English-first.
- CI exercises PHPUnit on every supported PHP version.
- Static analysis runs at an agreed level with clean baseline.
- Stability expectations for the public API are explicit.
- Domain Value Objects do not depend on AEM interfaces.
- At least two or three credible end-to-end examples exist.
- Documentation states when AEM is appropriate—and when Doctrine ORM should be used directly.
