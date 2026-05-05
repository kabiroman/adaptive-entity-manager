# ADR-003: Замена laminas-code на строковые шаблоны для генерации прокси-классов

| Поле            | Значение                                                        |
|-----------------|-----------------------------------------------------------------|
| Статус          | Proposed                                                        |
| Дата            | 2026-05-05                                                      |
| Автор           | Ruslan Kabirov                                                  |
| Затронутые файлы | `Factory/EntityMetadataFactory.php`, `Proxy/EntityProxyFactory.php`, `composer.json` |

## Контекст

Для реализации lazy-loading ассоциаций `hasOne` AEM генерирует прокси-классы — дочерние классы сущностей, которые делегируют вызовы лениво загружаемому оригиналу. Генерация выполняется с помощью библиотеки `laminas/laminas-code` ^4.0 в методе `EntityMetadataFactory::generateProxyClass()`.

Прокси-класс имеет простую структуру:

```php
namespace Proxy\Entity;

class UserProxy extends \App\Entity\User implements \Kabiroman\AEM\Proxy\ProxyInterface
{
    use \Kabiroman\AEM\Proxy\EntityProxyTrait;

    public function __getOriginal(): ?object
    {
        return $this->original;
    }

    public function __setCallback(\Closure $callback): void
    {
        $this->callback = $callback;
    }
}
```

Это минимальный класс с 2 методами и 1 trait. Для его генерации `laminas-code` создаёт объектную модель: `ClassGenerator` → `MethodGenerator` → `ParameterGenerator` → `TypeGenerator`, которая затем рендерится в PHP-код через `FileGenerator`. Это значительный overhead как по сложности, так и по производительности.

### Анализ зависимости laminas-code

| Метрика                        | Значение                                    |
|-------------------------------|---------------------------------------------|
| Размер пакета                 | ~1.2 MB (исходники)                         |
| Количество зависимостей       | `laminas/laminas-event-manager` (опц.)      |
| Транзитивные зависимости      | 4-6 пакетов                                 |
| Назначение                    | Полноценная генерация PHP-кодов (классы, файлы, лицензионные заголовки, докблоки) |
| Используемая функциональность | Генерация 1 класса с 2 методами и 1 trait  |

Использование `laminas-code` для генерации простейшего прокси-класса — это избыточность на порядок. Это как использовать кувалду для забивания гвоздя в плинтус.

## Решение

Заменить `laminas-code` на строковый шаблон (string template) для генерации прокси-классов. Шаблон — это обычная PHP-строка с плейсхолдерами для значений, которые меняются от сущности к сущности (namespace, имя класса, имя родительского класса).

### Реализация

```php
namespace Kabiroman\AEM\Proxy;

final class ProxyTemplateRenderer
{
    private const TEMPLATE = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace};

class {shortClassName} extends \{parentClassName} implements \Kabiroman\AEM\Proxy\ProxyInterface
{
    use \Kabiroman\AEM\Proxy\EntityProxyTrait;

    public function __getOriginal(): ?object
    {
        return $this->original;
    }

    public function __setCallback(\Closure $callback): void
    {
        $this->callback = $callback;
    }
}
PHP;

    public function render(
        string $namespace,
        string $shortClassName,
        string $parentClassName,
    ): string {
        return str_replace(
            ['{namespace}', '{shortClassName}', '{parentClassName}'],
            [$namespace, $shortClassName, $parentClassName],
            self::TEMPLATE,
        );
    }
}
```

### Валидация имён

Единственное, что нужно добавить сверх шаблона — валидацию входных параметров, чтобы предотвратить инъекцию произвольного кода через имена классов:

```php
private function validateClassName(string $name): void
{
    if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*$/', $name)) {
        throw new \InvalidArgumentException(
            sprintf('Invalid class name for proxy generation: "%s"', $name)
        );
    }
}
```

## Последствия

### Положительные

- **Удаление зависимости** `laminas/laminas-code` ^4.0 и её транзитивных зависимостей. Это сокращает `composer.lock` на 4-6 пакетов и уменьшает размер установки библиотеки.
- **Упрощение CI/CD** — меньше пакетов — быстрее `composer install`, меньше поверхность для уязвимостей.
- **Прозрачность** — шаблон прокси-класса читается как обычный PHP-код. Разработчик видит ровно то, что будет сгенерировано. Не нужно изучать API `ClassGenerator`/`MethodGenerator`, чтобы понять форму выходного кода.
- **Производительность** — `str_replace()` на строке ~300 байт работает на порядки быстрее, чем построение объектной модели laminas-code и её рендеринг. Хотя генерация происходит один раз (при warming кэша), разница существенна для тестов и dev-среды, где кэш часто сбрасывается.
- **Поддерживаемость** — изменение формы прокси-класса — это правка шаблона, а не навигация по API генератора.

### Отрицательные

- **Гибкость шаблона ограничена** — если в будущем потребуется генерировать прокси с дополнительными методами (например, `__clone()`, `__sleep()`, `__wakeup()`), шаблон нужно расширять. Для каждого варианта прокси — свой шаблон или условные блоки.
- **Ручная валидация** — нужно самостоятельно проверять имена классов на корректность. `laminas-code` делает это неявно через свой API.
- **Нет автформатирования** — сгенерированный код не проходит через PHP-CS-Fixer. Впрочем, это прокси-классы в кэше, их никто не читает.

## План миграции

1. Создать `ProxyTemplateRenderer` с шаблоном и валидацией.
2. Заменить вызовы `laminas-code` API в `EntityMetadataFactory::generateProxyClass()` на `$renderer->render()`.
3. Написать unit-тест, который генерирует прокси для тестовой сущности и проверяет, что:
   - Сгенерированный код валиден (`eval()` не бросает синтаксическую ошибку).
   - Класс корректно наследует сущность.
   - Методы `__getOriginal()` и `__setCallback()` присутствуют и работают.
   - `ProxyInterface` реализован.
4. Запустить существующие интеграционные тесты с новыми прокси — они должны пройти без изменений.
5. Удалить `laminas/laminas-code` из `composer.json`.
6. Выпустить как minor release с пометкой internal change.

## Альтернативы

### Альтернатива 1: Оставить laminas-code

Не трогать работающий код. "Если не сломано — не чинай".

**Минус**: зависимость, которая тянет за собой транзитивный граф пакетов ради генерации 20 строк кода. Для библиотеки, претендующей на лёгкость и адаптивность, это ненужный груз.

### Альтернатива 2: Doctrine Common ProxyGenerator

Использовать `Doctrine\Common\Proxy\ProxyGenerator`, который входит в `doctrine/common` — зависимость, которая и так косвенно может присутствовать через `doctrine/persistence`.

**Минус**: `ProxyGenerator` из Doctrine гораздо сложнее, чем нужно AEM. Он генерирует прокси с `__load()`, `__isInitialized()`, `__setInitialized()`, `__setInitializer()`, `__getInitializer()`, `__setCloner()`, `__getCloner()`, `__sleep()`, `__wakeup()`, `__clone()` — 10 методов вместо 2. Это over-engineering для текущих потребностей AEM.

### Альтернатива 3: eval() вместо записи в файл

Генерировать код через шаблон и выполнять через `eval()` вместо записи в файл на диск.

**Минус**: `eval()` невозможно кэшировать между запросами, код компилируется при каждом запросе. Для production это неприемлемо по производительности. Подходит только для dev-режима.
