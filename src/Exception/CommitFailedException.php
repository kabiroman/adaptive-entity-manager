<?php

namespace Kabiroman\AEM\Exception;

use Exception;
use Throwable;

class CommitFailedException extends Exception
{
    public function __construct(Throwable $previous = null)
    {
        parent::__construct("Commit failed!", 0, $previous);
    }
}
