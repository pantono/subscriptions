<?php

namespace Pantono\Subscriptions\Model;

use Pantono\Contracts\Application\Interfaces\SavableInterface;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\DatabaseTable;

#[DatabaseTable('subscription_status')]
class SubscriptionStatus implements SavableInterface
{
    use SavableModel;

    private ?int $id = null;
    private string $name;
    private bool $active;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
