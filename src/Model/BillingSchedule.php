<?php

namespace Pantono\Subscriptions\Model;

class BillingSchedule
{
    public function validate(Subscription $subscription): void
    {
        $type = $subscription->getType();
        if ($type === null || $type->getId() === null) {
            throw new \InvalidArgumentException('A saved subscription type is required');
        }
        if ($type->getBillingIntervalCount() <= 0 || !in_array($type->getBillingIntervalUnit(), ['day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years'], true)) {
            throw new \InvalidArgumentException('Billing intervals must be a positive number of days, weeks, months or years');
        }
        if ($subscription->getPriceInPence() < 0) {
            throw new \InvalidArgumentException('Subscription price cannot be negative');
        }
        if ($subscription->getCustomer() !== null && !$type->isAllowCustomer()) {
            throw new \InvalidArgumentException('This subscription type does not allow customers');
        }
        if ($subscription->getCompany() !== null && !$type->isAllowCompany()) {
            throw new \InvalidArgumentException('This subscription type does not allow companies');
        }
        if ($subscription->getNextBillingDate() < $subscription->getStartDate()) {
            throw new \InvalidArgumentException('Billing cannot begin before the subscription starts');
        }
        if ($subscription->getEndDate() !== null && $subscription->getEndDate() <= $subscription->getStartDate()) {
            throw new \InvalidArgumentException('Subscription end must be after its start');
        }
    }

    public function getPeriodEnd(Subscription $subscription): \DateTimeImmutable
    {
        $this->validate($subscription);
        $start = \DateTimeImmutable::createFromInterface($subscription->getNextBillingDate());
        $type = $subscription->getType();
        $unit = rtrim($type->getBillingIntervalUnit(), 's');
        $count = $type->getBillingIntervalCount();
        if ($unit === 'month' || $unit === 'year') {
            $anchor = $subscription->getStartDate();
            $target = $start->setDate((int)$start->format('Y'), (int)$start->format('m'), 1);
            $target = $target->modify('+' . $count . ' ' . $unit);
            $day = $anchor->format('j') === $anchor->format('t') ? (int)$target->format('t') : min((int)$anchor->format('j'), (int)$target->format('t'));
            $end = $target->setDate((int)$target->format('Y'), (int)$target->format('m'), $day);
        } else {
            $end = $start->modify('+' . $count . ' ' . $unit);
        }
        if ($subscription->getEndDate() !== null && $end > $subscription->getEndDate()) {
            $end = \DateTimeImmutable::createFromInterface($subscription->getEndDate());
        }
        if ($end <= $start) {
            throw new \InvalidArgumentException('The billing period must have a positive duration');
        }
        return $end;
    }
}
