<?php

namespace Pantono\Subscriptions\Model;

use Pantono\Contracts\Application\Interfaces\SavableInterface;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\Database\OneToOne;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Customers\Model\Customer;
use Pantono\Customers\Model\Company;
use Pantono\Payments\Model\PaymentMandate;
use Pantono\Contracts\Attributes\FieldName;

#[DatabaseTable('subscription')]
class Subscription implements SavableInterface
{
    use SavableModel;

    private ?int $id = null;
    private \DateTimeInterface $dateCreated;
    private \DateTimeInterface $dateUpdated;
    #[OneToOne(targetModel: SubscriptionStatus::class), FieldName('status_id')]
    private ?SubscriptionStatus $status = null;
    #[OneToOne(targetModel: SubscriptionType::class), FieldName('type_id')]
    private ?SubscriptionType $type = null;
    #[OneToOne(targetModel: Customer::class), FieldName('customer_id')]
    private ?Customer $customer = null;
    #[OneToOne(targetModel: Company::class), FieldName('company_id')]
    private ?Company $company = null;
    private \DateTimeInterface $startDate;
    private ?\DateTimeInterface $endDate = null;
    private int $priceInPence;
    #[OneToOne(targetModel: PaymentMandate::class), FieldName('payment_method_id')]
    private ?PaymentMandate $paymentMethod = null;
    private \DateTimeInterface $nextBillingDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getDateCreated(): \DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeInterface $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateUpdated(): \DateTimeInterface
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(\DateTimeInterface $dateUpdated): void
    {
        $this->dateUpdated = $dateUpdated;
    }

    public function getStatus(): ?SubscriptionStatus
    {
        return $this->status;
    }

    public function setStatus(?SubscriptionStatus $status): void
    {
        $this->status = $status;
    }

    public function getType(): ?SubscriptionType
    {
        return $this->type;
    }

    public function setType(?SubscriptionType $type): void
    {
        $this->type = $type;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): void
    {
        $this->company = $company;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): void
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

    public function getPaymentMethod(): ?PaymentMandate
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMandate $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getNextBillingDate(): \DateTimeInterface
    {
        return $this->nextBillingDate;
    }

    public function setNextBillingDate(\DateTimeInterface $nextBillingDate): void
    {
        $this->nextBillingDate = $nextBillingDate;
    }
}
