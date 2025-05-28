<?php

namespace Kabiroman\AEM\DataAdapter;

use function Symfony\Component\String\u;

abstract class AbstractDataAdapter implements EntityDataAdapter
{
    protected function toCamelCaseParams(array &$row): void
    {
        foreach ($row as $key => $value) {
            $this->changeKey($key, u($key)->lower()->camel()->toString(), $row);
        }
    }

    protected function toSnakeCaseParams(array &$row): void
    {
        foreach ($row as $key => $value) {
            $this->changeKey($key, u($key)->snake()->toString(), $row);
        }
    }

    protected function toUpperSnakeCaseParams(array &$row): void
    {
        foreach ($row as $key => $value) {
            $this->changeKey($key, u($key)->snake()->upper()->toString(), $row);
        }
    }

    protected function changeKey($key, $new_key, &$arr): void
    {
        if (!array_key_exists($new_key, $arr)) {
            $arr[$new_key] = $arr[$key];
            unset($arr[$key]);
        }
    }
}
