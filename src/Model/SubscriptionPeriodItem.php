<?php

namespace Pantono\Subscriptions\Model;

use Pantono\Contracts\Application\Interfaces\SavableInterface;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Contracts\Attributes\Database\OneToOne;
use Pantono\Contracts\Attributes\FieldName;
use Pantono\Contracts\Attributes\Filter;

#[DatabaseTable('subscription_period_item')]
class SubscriptionPeriodItem implements SavableInterface
{
    use SavableModel;

    private ?int $id = null;
    private int $periodId;
    #[OneToOne(targetModel: SubscriptionTypeItem::class), FieldName('type_id')]
    private ?SubscriptionTypeItem $type = null;
    private \DateTimeInterface $date;
    private int $quantity;
    /**
     * @var array<mixed>
     */
    #[Filter('json_decode')]
    private array $metadata = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getPeriodId(): int
    {
        return $this->periodId;
    }

    public function setPeriodId(int $periodId): void
    {
        $this->periodId = $periodId;
    }

    public function getType(): ?SubscriptionTypeItem
    {
        return $this->type;
    }

    public function setType(?SubscriptionTypeItem $type): void
    {
        $this->type = $type;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
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
}
