<?php

namespace Pantono\Subscriptions\Filter;

use Pantono\Contracts\Filter\PageableInterface;
use Pantono\Database\Traits\Pageable;
use Pantono\Contracts\Application\Interfaces\SortableInterface;
use Pantono\Database\Filter\SortableFilter;
use Pantono\Customers\Model\Customer;
use Pantono\Customers\Model\Company;
use Pantono\Subscriptions\Model\SubscriptionStatus;

class SubscriptionFilter extends SortableFilter implements PageableInterface, SortableInterface
{
    use Pageable;

    public function getSortableFields(): array
    {
        return [
            'id', 'date_created', 'date_updated', 'start_date', 'end_date', 'price_in_pence'
        ];
    }

    private ?Customer $customer = null;
    private ?Company $company = null;
    private ?SubscriptionStatus $status = null;
    private ?\DateTimeInterface $nextBillingDateStart = null;
    private ?\DateTimeInterface $nextBillingDateEnd = null;

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

    public function getStatus(): ?SubscriptionStatus
    {
        return $this->status;
    }

    public function setStatus(?SubscriptionStatus $status): void
    {
        $this->status = $status;
    }

    public function getNextBillingDateStart(): ?\DateTimeInterface
    {
        return $this->nextBillingDateStart;
    }

    public function setNextBillingDateStart(?\DateTimeInterface $nextBillingDateStart): void
    {
        $this->nextBillingDateStart = $nextBillingDateStart;
    }

    public function getNextBillingDateEnd(): ?\DateTimeInterface
    {
        return $this->nextBillingDateEnd;
    }

    public function setNextBillingDateEnd(?\DateTimeInterface $nextBillingDateEnd): void
    {
        $this->nextBillingDateEnd = $nextBillingDateEnd;
    }
}
