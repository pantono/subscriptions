<?php

namespace Pantono\Subscriptions\Repository;

use Pantono\Database\Repository\DefaultRepository;
use Pantono\Subscriptions\Filter\SubscriptionFilter;
use Pantono\Subscriptions\Filter\SubscriptionPeriodFilter;
use Pantono\Subscriptions\Model\SubscriptionPeriod;

class SubscriptionsRepository extends DefaultRepository
{

    /**
     * @return array<int,mixed>
     */
    public function getSubscriptionsByFilter(SubscriptionFilter $filter): array
    {
        $select = $this->getDb()->select('s.*')->from('subscription', 's');

        if ($filter->getCustomer() !== null) {
            $select->andWhere('s.customer_id=:customer_id')
                ->setParameter('customer_id', $filter->getCustomer()->getId());
        }
        if ($filter->getCompany() !== null) {
            $select->andWhere('s.company_id=:company_id')
                ->setParameter('company_id', $filter->getCompany()->getId());
        }
        if ($filter->getNextBillingDateStart() !== null) {
            $select->andWhere('s.next_billing_date>=:next_billing_date_start')
                ->setParameter('next_billing_date_start', $filter->getNextBillingDateStart()->format('Y-m-d H:i:s'));
        }
        if ($filter->getNextBillingDateEnd() !== null) {
            $select->andWhere('s.next_billing_date<=:next_billing_date_end')
                ->setParameter('next_billing_date_end', $filter->getNextBillingDateEnd()->format('Y-m-d H:i:s'));
        }

        if ($filter->getStatus() !== null) {
            $select->andWhere('s.status_di=:status')
                ->setParameter('status', $filter->getStatus()->getId());
        }
        $this->applySort($select, $filter);
        $this->applyCountAndLimit($select, $filter);
        return $this->getDb()->fetchAll($select);
    }

    /**
     * @param SubscriptionPeriodFilter $filter
     * @return array<int,mixed>
     */
    public function getSubscriptionPeriodsByFilter(SubscriptionPeriodFilter $filter): array
    {
        $select = $this->getDb()->select('p.*')->from('subscripion_period', 'p');

        if ($filter->getDateWithin() !== null) {
            $select->andWhere('start_date <= :date_within and end_date >= :date_within')
                ->setParameter('date_within', $filter->getDateWithin()->format('Y-m-d H:i:s'));
        }
        if ($filter->getStartDateFrom() !== null) {
            $select->andWhere('start_date >= :start_date_from')
                ->setParameter('start_date_from', $filter->getStartDateFrom()->format('Y-m-d H:i:s'));
        }
        if ($filter->getStartDateTo() !== null) {
            $select->andWhere('start_date <= :start_date_to')
                ->setParameter('start_date_to', $filter->getStartDateTo()->format('Y-m-d H:i:s'));
        }

        if ($filter->getEndDateFrom() !== null) {
            $select->andWhere('end_date >= :end_date_from')
                ->setParameter('end_date_from', $filter->getEndDateFrom()->format('Y-m-d H:i:s'));
        }
        if ($filter->getEndDateTo() !== null) {
            $select->andWhere('end_date <= :end_date_to')
                ->setParameter('end_date_to', $filter->getEndDateTo()->format('Y-m-d H:i:s'));
        }

        if ($filter->getMinPriceInPence() !== null) {
            $select->andWhere('price_in_pence >= :min_price_in_pence')
                ->setParameter('min_price_in_pence', $filter->getMinPriceInPence());
        }
        if ($filter->getMaxPriceInPence() !== null) {
            $select->andWhere('price_in_pence <= :max_price_in_pence')
                ->setParameter('max_price_in_pence', $filter->getMaxPriceInPence());
        }

        $this->applySort($select, $filter);
        $this->applyCountAndLimit($select, $filter);

        return $this->getDb()->fetchAll($select);
    }

    public function saveSubscriptionPeriod(SubscriptionPeriod $period): void
    {
        $this->saveModel($period);
        $this->getDb()->createQueryBuilder()->delete('p.*')->from('subscription_period_payment', 'p')
            ->andWhere('p.period.id = :period_id')
            ->setParameter('period_id', $period->getId())
            ->executeQuery();
        foreach ($period->getPayments() as $payment) {
            $this->getDb()->insert('subscription_period_payment', ['period_id' => $period->getId(), 'payment_id' => $payment->getId()]);
        }
    }
}
