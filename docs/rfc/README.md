# Adaptive Entity Manager — Code Review: ADR & RFC Index

Обзорная страница, связывающая все архитектурные решения (ADR) и запросы на изменение (RFC), составленные по результатам code review репозитория [kabiroman/adaptive-entity-manager](https://github.com/kabiroman/adaptive-entity-manager) v1.6.0.

## Реализовано (MVP в ядре)

- **[RFC-003](./RFC-003-domain-agnostic-value-object-mapping.md)** — маппинг доменных value object без `ValueObjectInterface` (Phase 1, **v1.6+**). Остальные фазы в документе — дорожная карта.

---

## Приоритизация

Документы упорядочены по приоритету внедрения — от критичных к желательным:

| # | Документ | Тип | Приоритет | Суть |
|---|----------|-----|-----------|------|
| 1 | [ADR-001](./ADR-001-remove-static-entity-factory.md) | ADR | 🔴 High | Устранение статической EntityFactory — breaking bug для long-running runtime |
| 2 | [ADR-002](./ADR-002-replace-metadata-arrays-with-typed-dtos.md) | ADR | 🔴 High | Замена сырых массивов метаданных на типизированные DTO — опечатки, type-safety |
| 3 | [RFC-001](./RFC-001-batch-operations-interface.md) | RFC | 🟡 Medium | Пакетные операции для EntityDataAdapter — производительность SQL |
| 4 | [RFC-002](./RFC-002-phpstan-level-8-roadmap.md) | RFC | 🟡 Medium | Дорожная карта PHPStan Level 5 → 8 — качество библиотеки |
| 5 | [ADR-003](./ADR-003-replace-laminas-code-with-string-templates.md) | ADR | 🟡 Medium | Замена laminas-code на строковые шаблоны — удаление зависимости |
| 6 | [ADR-004](./ADR-004-formalize-event-dispatch-order.md) | ADR | 🟢 Low | Формализация контракта порядка событий — предотвращение регрессий |

---

## Зависимости между документами

```
ADR-001 (static EntityFactory)
  └── независим, можно внедрять сразу

ADR-002 (metadata DTOs)
  └── независим, но RFC-002 (PHPStan 8) значительно упрощается после внедрения
      └── RFC-002 (PHPStan 8) получает больше информации о типах из DTO

RFC-001 (batch operations)
  └── зависит от ADR-004 (event order) — пакетные операции меняют семантику lifecycle events

ADR-003 (laminas-code removal)
  └── независим, можно внедрять сразу

ADR-004 (event order)
  └── RFC-001 (batch) должен учитывать формализованный порядок
```

---

## Рекомендуемый порядок внедрения

### Спринт 1 (быстрые победы)
1. **ADR-003** — удаление laminas-code. Минимальный риск, мгновенный выигрыш (меньше зависимостей).
2. **ADR-001** — устранение статики. Прямой bug-fix для long-running сред.

### Спринт 2 (качество)
3. **ADR-002** — metadata DTOs. Крупное изменение, но с поэтапной миграцией.
4. **RFC-002** — PHPStan level 6 (первая фаза). Начать одновременно с ADR-002.

### Спринт 3 (функциональность)
5. **ADR-004** — формализация порядка событий. Предусловие для RFC-001.
6. **RFC-001** — пакетные операции. Фаза 1 (интерфейс + дефолт).
7. **RFC-002** — PHPStan level 7-8. Продолжение после стабилизации ADR-002.

---

## Формат документов

- **ADR** (Architecture Decision Record) — документирует принятое архитектурное решение с контекстом, рассуждениями, последствиями и альтернативами. Формат: Context → Decision → Consequences → Alternatives.
- **RFC** (Request for Comments) — предложение по изменению с мотивацией, детальным решением, планом реализации и альтернативами. Формат: Motivation → Solution → Implementation Plan → Alternatives.
