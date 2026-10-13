<?php

namespace Pantono\Subscriptions\Model;

use Pantono\Contracts\Application\Interfaces\SavableInterface;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\Database\OneToMany;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Payments\Model\Payment;
use Pantono\Contracts\Attributes\Database\ManyToMany;
use Pantono\Contracts\Attributes\Database\OneToOne;
use Pantono\Contracts\Attributes\FieldName;

#[DatabaseTable('subscription_period')]
class SubscriptionPeriod implements SavableInterface
{
    use SavableModel;

    private ?int $id = null;
    #[OneToOne(targetModel: Subscription::class), FieldName('subscription_id')]
    private ?Subscription $subscription = null;
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
    private ?\DateTimeInterface $dateLastAttempted = null;
    private ?string $paymentError = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): void
    {
        $this->subscription = $subscription;
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

    public function addPayment(Payment $payment): void
    {
        $this->payments[] = $payment;
    }

    public function getDateLastAttempted(): ?\DateTimeInterface
    {
        return $this->dateLastAttempted;
    }

    public function setDateLastAttempted(?\DateTimeInterface $dateLastAttempted): void
    {
        $this->dateLastAttempted = $dateLastAttempted;
    }

    public function getPaymentDescriptor(): string
    {
        return sprintf('Payment for %s from %s to %s', $this->getSubscription()->getType()->getName(), $this->getStartDate()->format('d/m/Y'), $this->getEndDate()->format('d/m/Y'));
    }

    public function getPaymentError(): ?string
    {
        return $this->paymentError;
    }

    public function setPaymentError(?string $paymentError): void
    {
        $this->paymentError = $paymentError;
    }

    public function isPaid(): bool
    {
        $total = 0;
        foreach ($this->getPayments() as $payment) {
            if ($payment->getStatus()->isCompleted()) {
                $total += $payment->getAmount();
            }
        }
        return $total >= $this->getPriceInPence();
    }

    public function isPendingPaid(): bool
    {
        $total = 0;
        foreach ($this->getPayments() as $payment) {
            if ($payment->getStatus()->isCompleted() || $payment->getStatus()->isPending()) {
                $total += $payment->getAmount();
            }
        }
        return $total >= $this->getPriceInPence();
    }

}
