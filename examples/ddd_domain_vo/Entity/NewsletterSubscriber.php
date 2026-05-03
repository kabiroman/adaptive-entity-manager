<?php

declare(strict_types=1);

namespace Examples\Ddd\Entity;

use Examples\Ddd\Domain\EmailAddress;

final class NewsletterSubscriber
{
    private int $id;
    private string $name;
    private EmailAddress $email;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function setEmail(EmailAddress $email): void
    {
        $this->email = $email;
    }
}
