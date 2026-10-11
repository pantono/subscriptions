<?php

namespace Pantono\Subscriptions\Model;

use Pantono\Contracts\Attributes\DatabaseTable;

#[DatabaseTable('subscription_type_item')]
class SubscriptionTypeItem
{
    private ?int $id = null;
    private int $subscriptionTypeId;
    private string $name;
    private int $quantity;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSubscriptionTypeId(): int
    {
        return $this->subscriptionTypeId;
    }

    public function setSubscriptionTypeId(int $subscriptionTypeId): void
    {
        $this->subscriptionTypeId = $subscriptionTypeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
}
