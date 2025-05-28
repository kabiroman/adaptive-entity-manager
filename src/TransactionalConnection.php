<?php

namespace Kabiroman\AEM;

interface TransactionalConnection
{
    public function beginTransaction();

    public function rollbackTransaction();

    public function commitTransaction();
}
