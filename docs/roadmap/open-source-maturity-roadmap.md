---
name: project-maturity-roadmap
overview: "План переводит Adaptive Entity Manager из состояния ранней публичной библиотеки к более зрелому OSS-пакету: стабильнее API, лучше документация, CI, статический анализ, примеры и более чистая DDD-архитектура."
todos:
  - id: docs-positioning
    content: Обновить позиционирование, README и англоязычную документацию.
    status: pending
  - id: quality-gates
    content: Добавить CI, PHPUnit matrix, static analysis и coding style checks.
    status: pending
  - id: api-stability
    content: Определить public/internal API, CHANGELOG и release policy.
    status: pending
  - id: vo-mapping
    content: Спроектировать и реализовать domain-agnostic conversion layer для VO.
    status: pending
  - id: ddd-examples
    content: Добавить DDD aggregate mapping guidance и end-to-end examples.
    status: pending
  - id: ecosystem-sync
    content: Синхронизировать ядро с Symfony bundle и OpenTelemetry bridge.
    status: pending
isProject: false
---

# План повышения зрелости AEM

## Цель

Довести проект до состояния, в котором его не стыдно активно показывать внешним PHP-разработчикам: понятное позиционирование, английская документация, проверяемое качество, стабильнее API и убедительные примеры использования.

## Текущее состояние

Проект уже имеет хорошую базу: Composer package, публичный namespace, README, тесты, metadata/entity manager/unit of work/data adapter архитектуру. Основная ценность проекта — adaptive mapper для legacy и mixed data sources, а не попытка заменить Doctrine ORM.

Ключевые точки входа:

- [/var/www/personal/adaptive-entity-manager/composer.json](/var/www/personal/adaptive-entity-manager/composer.json) — пакет, зависимости и тестовые команды.
- [/var/www/personal/adaptive-entity-manager/src/AdaptiveEntityManager.php](/var/www/personal/adaptive-entity-manager/src/AdaptiveEntityManager.php) — основной публичный API.
- [/var/www/personal/adaptive-entity-manager/src/UnitOfWork.php](/var/www/personal/adaptive-entity-manager/src/UnitOfWork.php) — commit/transaction lifecycle.
- [/var/www/personal/adaptive-entity-manager/src/EntityPersister.php](/var/www/personal/adaptive-entity-manager/src/EntityPersister.php) — persistence flow и dirty tracking.
- [/var/www/personal/adaptive-entity-manager/docs/rfc/domain-agnostic-value-object-mapping.md](/var/www/personal/adaptive-entity-manager/docs/rfc/domain-agnostic-value-object-mapping.md) — направление по чистому VO mapping.

## Этап 1: Упаковка и доверие

Сначала стоит улучшить внешний вид проекта без изменения runtime-поведения.

- Перевести ключевую документацию на английский и сделать английский canonical language.
- Обновить README: clearly state purpose, non-goals, installation, minimal example, adapter example, limitations.
- Добавить бейджи после настройки CI: tests, PHP version, license, Packagist version.
- Явно описать статус проекта: experimental, beta, stable API или migration-oriented library.
- Разделить документацию на user docs, architecture docs, RFC/ADR.

Результат этапа: внешний пользователь понимает, зачем нужен AEM, где его применять и какие ограничения учитывать.

## Этап 2: Автоматическое качество

Дальше нужно сделать так, чтобы базовое качество проверялось автоматически.

- Добавить GitHub Actions для PHPUnit на нескольких PHP-версиях.
- Добавить PHPStan или Psalm с умеренным стартовым уровнем.
- Добавить PHP-CS-Fixer или ECS для единого стиля.
- Убрать из Composer scripts shell-зависимости, если они мешают кроссплатформенности.
- Зафиксировать минимальный quality gate: tests + static analysis + coding style.

Результат этапа: проект вызывает больше доверия как библиотека, а не только как идея.

## Этап 3: Стабилизация публичного API

Перед активным продвижением стоит определить, какие интерфейсы являются стабильными контрактами.

- Разделить public API и internal classes в документации.
- Проверить интерфейсы `EntityManagerInterface`, `PersisterInterface`, `EntityDataAdapter`, `ClassMetadata`.
- Решить, какие API можно менять в minor versions, а какие требуют major release.
- Добавить CHANGELOG и простую release policy.
- Начать фиксировать архитектурные решения через ADR после RFC.

Результат этапа: пользователи понимают, на какие контракты можно опираться.

## Этап 4: Domain-Agnostic Value Object Mapping

Это главный архитектурный шаг для DDD-совместимости.

- Не заменять `ValueObjectInterface` напрямую на `Stringable`.
- Ввести общий conversion layer, который не зависит от AEM-интерфейсов в домене.
- Оставить `ValueObjectInterface` как backward-compatible convention.
- Добавить поддержку converters через metadata options: `class`, `converter`, `from`, `to`, `format`.
- Поддержать built-in cases: `Stringable`, `BackedEnum`, `JsonSerializable`, `DateTimeInterface`.
- Обновить документацию `VALUE_OBJECTS.md`: новые примеры должны показывать чистые доменные VO.

Результат этапа: доменный слой не обязан зависеть от `Kabiroman\AEM\ValueObject\ValueObjectInterface`.

## Этап 5: DDD Aggregate Mapping

После VO mapping можно аккуратно развивать AEM как mapper для агрегатов.

- Описать рекомендуемый pattern: repository работает с aggregate root, не с любой внутренней entity.
- Добавить metadata option для aggregate root только если это реально нужно API.
- Исследовать reconstitution через named constructors или factory methods.
- Ограничить или явно задокументировать lazy loading внутри aggregate boundary.
- Добавить пример `Order` aggregate с `OrderId`, `Email`, `OrderStatus`, adapter и repository.

Результат этапа: AEM получает сильную нишу — не “ещё одна ORM”, а mapper для legacy/mixed sources и DDD-моделей.

## Этап 6: Примеры и сценарии использования

Для OSS-библиотеки важны не только API, но и быстрый путь к пониманию.

- Добавить `examples/legacy-db` с простым adapter.
- Добавить `examples/rest-api-adapter`.
- Добавить `examples/ddd-aggregate`.
- Добавить короткие integration tests или smoke tests для примеров.
- Обновить README ссылками на эти сценарии.

Результат этапа: новый пользователь может за 10-15 минут понять практическую пользу библиотеки.

## Этап 7: Наблюдаемость и экосистема

Этот этап важен после стабилизации ядра.

- Согласовать API ядра с Symfony bundle.
- Проверить, что OpenTelemetry bridge покрывает ключевые lifecycle operations.
- Документировать extension points: adapters, converters, metadata providers, repositories.
- Подготовить migration guide для пользователей старого VO API.

Результат этапа: вокруг AEM появляется понятная экосистема, а не только standalone package.

## Рекомендуемый порядок работ

1. Сначала документация и CI, потому что это дешево и сразу повышает доверие.
2. Затем static analysis и coding style, чтобы дальнейшие изменения были безопаснее.
3. Потом RFC по VO перевести в реализацию через converter layer.
4. После этого делать DDD aggregate examples и reconstitution API.
5. В конце синхронизировать Symfony bundle и observability bridge.

## Критерий хорошего уровня

Проект можно считать на хорошем уровне, когда:

- README и основные docs на английском.
- CI стабильно гоняет PHPUnit на поддерживаемых PHP-версиях.
- Static analysis проходит на согласованном уровне.
- Есть понятный статус стабильности API.
- Domain VO не обязаны реализовывать AEM-интерфейсы.
- Есть минимум два-три рабочих end-to-end examples.
- Документация ясно говорит, где AEM уместен, а где лучше использовать Doctrine напрямую.