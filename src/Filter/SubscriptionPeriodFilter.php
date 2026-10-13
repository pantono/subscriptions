<?php

namespace Pantono\Subscriptions\Filter;

use Pantono\Database\Filter\SortableFilter;
use Pantono\Contracts\Filter\PageableInterface;
use Pantono\Database\Traits\Pageable;
use Pantono\Subscriptions\Model\Subscription;

class SubscriptionPeriodFilter extends SortableFilter implements PageableInterface
{
    use Pageable;

    private ?Subscription $subscription = null;
    private ?\DateTimeInterface $startDateFrom = null;
    private ?\DateTimeInterface $startDateTo = null;
    private ?\DateTimeInterface $dateWithin = null;
    private ?\DateTimeInterface $endDateFrom = null;
    private ?\DateTimeInterface $endDateTo = null;
    private ?int $minPriceInPence = null;
    private ?int $maxPriceInPence = null;

    public function getSortableFields(): array
    {
        return [
            'id', 'start_date', 'end_date', 'price_in_pence'
        ];
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function getStartDateFrom(): ?\DateTimeInterface
    {
        return $this->startDateFrom;
    }

    public function setStartDateFrom(?\DateTimeInterface $startDateFrom): void
    {
        $this->startDateFrom = $startDateFrom;
    }

    public function getStartDateTo(): ?\DateTimeInterface
    {
        return $this->startDateTo;
    }

    public function setStartDateTo(?\DateTimeInterface $startDateTo): void
    {
        $this->startDateTo = $startDateTo;
    }

    public function getDateWithin(): ?\DateTimeInterface
    {
        return $this->dateWithin;
    }

    public function setDateWithin(?\DateTimeInterface $dateWithin): void
    {
        $this->dateWithin = $dateWithin;
    }

    public function getEndDateFrom(): ?\DateTimeInterface
    {
        return $this->endDateFrom;
    }

    public function setEndDateFrom(?\DateTimeInterface $endDateFrom): void
    {
        $this->endDateFrom = $endDateFrom;
    }

    public function getEndDateTo(): ?\DateTimeInterface
    {
        return $this->endDateTo;
    }

    public function setEndDateTo(?\DateTimeInterface $endDateTo): void
    {
        $this->endDateTo = $endDateTo;
    }

    public function getMinPriceInPence(): ?int
    {
        return $this->minPriceInPence;
    }

    public function setMinPriceInPence(?int $minPriceInPence): void
    {
        $this->minPriceInPence = $minPriceInPence;
    }

    public function getMaxPriceInPence(): ?int
    {
        return $this->maxPriceInPence;
    }

    public function setMaxPriceInPence(?int $maxPriceInPence): void
    {
        $this->maxPriceInPence = $maxPriceInPence;
    }
}
