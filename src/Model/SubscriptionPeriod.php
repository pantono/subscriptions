<?php

namespace Pantono\Subscriptions\Model;

use Pantono\Contracts\Application\Interfaces\SavableInterface;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\Database\OneToMany;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Payments\Model\Payment;
use Pantono\Contracts\Attributes\Database\ManyToMany;

#[DatabaseTable('subscription_period')]
class SubscriptionPeriod implements SavableInterface
{
    use SavableModel;

    private ?int $id = null;
    private int $subscriptionId;
    private \DateTimeInterface $startDate;
    private \DateTimeInterface $endDate;
    private int $priceInPence;
    /**
     * @var SubscriptionPeriodItem[]
     */
    #[OneToMany(targetModel: SubscriptionPeriodItem::class, mappedBy: 'period_id')]
    private array $items = [];
    /**
     * @var Payment[]
     */
    #[ManyToMany(joinTable: 'subscription_period_payment', joinColumn: 'period_id', inverseJoinColumn: 'payment_id', targetModel: Payment::class)]
    private array $payments = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSubscriptionId(): int
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(int $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getPriceInPence(): int
    {
        return $this->priceInPence;
    }

    public function setPriceInPence(int $priceInPence): void
    {
        $this->priceInPence = $priceInPence;
    }

    /**
     * @return SubscriptionPeriodItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    /**
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    public function setPayments(array $payments): void
    {
        $this->payments = $payments;
    }
}
