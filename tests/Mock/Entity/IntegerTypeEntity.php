<?php

namespace Kabiroman\AEM\Tests\Mock\Entity;

class IntegerTypeEntity
{
    private int $id;
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
