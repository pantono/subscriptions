<?php

namespace Pantono\Subscriptions\Model;

use Pantono\Contracts\Application\Interfaces\SavableInterface;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\Filter;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Contracts\Attributes\Database\OneToMany;

#[DatabaseTable('subscription_type')]
class SubscriptionType implements SavableInterface
{
    use SavableModel;

    private ?int $id = null;
    private string $name;
    private string $description;
    private bool $allowCustomer;
    private bool $allowCompany;
    private int $priceInPence;
    private bool $active;
    /**
     * @var array<mixed>
     */
    #[Filter('json_decode')]
    private array $metadata = [];
    /**
     * @var SubscriptionTypeItem[]
     */
    #[OneToMany(targetModel: SubscriptionTypeItem::class, mappedBy: 'subscription_type_id')]
    private array $items = [];
    private string $billingIntervalUnit;
    private int $billingIntervalCount;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isAllowCustomer(): bool
    {
        return $this->allowCustomer;
    }

    public function setAllowCustomer(bool $allowCustomer): void
    {
        $this->allowCustomer = $allowCustomer;
    }

    public function isAllowCompany(): bool
    {
        return $this->allowCompany;
    }

    public function setAllowCompany(bool $allowCompany): void
    {
        $this->allowCompany = $allowCompany;
    }

    public function getPriceInPence(): int
    {
        return $this->priceInPence;
    }

    public function setPriceInPence(int $priceInPence): void
    {
        $this->priceInPence = $priceInPence;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * @return mixed[]
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * @return SubscriptionTypeItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getBillingIntervalUnit(): string
    {
        return $this->billingIntervalUnit;
    }

    public function setBillingIntervalUnit(string $billingIntervalUnit): void
    {
        $this->billingIntervalUnit = $billingIntervalUnit;
    }

    public function getBillingIntervalCount(): int
    {
        return $this->billingIntervalCount;
    }

    public function setBillingIntervalCount(int $billingIntervalCount): void
    {
        $this->billingIntervalCount = $billingIntervalCount;
    }
}
