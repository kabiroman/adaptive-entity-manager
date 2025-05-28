<?php

namespace Kabiroman\AEM;

interface ConfigInterface
{
    public function getEntityFolder(): string;

    public function getEntityNamespace(): string;

    public function getCacheFolder(): string;
}