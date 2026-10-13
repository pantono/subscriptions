<?php

namespace Pantono\Subscriptions\Repository;

use Pantono\Database\Repository\DefaultRepository;
use Pantono\Subscriptions\Filter\SubscriptionFilter;
use Pantono\Subscriptions\Filter\SubscriptionPeriodFilter;
use Pantono\Subscriptions\Model\SubscriptionPeriod;
use Pantono\Subscriptions\Model\Subscription;
use Pantono\Subscriptions\Subscriptions;

class SubscriptionsRepository extends DefaultRepository
{

    /**
     * @return array<int,mixed>
     */
    public function getSubscriptionsByFilter(SubscriptionFilter $filter): array
    {
        $select = $this->getDb()->select('s.*')->from($this->pt('subscription'), 's');

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
            $select->andWhere('s.status_id=:status')
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
        $select = $this->getDb()->select('p.*')->from($this->pt('subscription_period'), 'p');

        if ($filter->getDateWithin() !== null) {
            $select->andWhere('start_date <= :date_within and end_date > :date_within')
                ->setParameter('date_within', $filter->getDateWithin()->format('Y-m-d H:i:s'));
        }

        if ($filter->getSubscription() !== null) {
            $select->andWhere('p.subscription_id=:subscription_id')
                ->setParameter('subscription_id', $filter->getSubscription()->getId());
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
        $previousId = $period->getId();
        $this->beginTransaction();
        try {
            if ($previousId === null) {
                $this->saveModel($period);
            } else {
                $current = $this->lookupRecord(SubscriptionPeriod::class, $previousId);
                if ($current === null || (int)$current['subscription_id'] !== $period->getSubscription()->getId() || $current['start_date'] !== $period->getStartDate()->format('Y-m-d H:i:s')) {
                    throw new \InvalidArgumentException('A saved renewal identity cannot be changed');
                }
                $data = $period->getAllData();
                unset($data['date_last_attempted']);
                $this->getDb()->update($this->pt('subscription_period'), $data, ['id' => $previousId]);
                $period->setDateLastAttempted($current['date_last_attempted'] === null ? null : new \DateTimeImmutable($current['date_last_attempted']));
            }
            $this->getDb()->delete($this->pt('subscription_period_payment'), ['period_id' => $period->getId()]);
            $ids = [];
            foreach ($period->getPayments() as $payment) {
                if ($payment->getId() === null) {
                    throw new \InvalidArgumentException('Only saved payments can be linked to a period');
                }
                if (!in_array($payment->getId(), $ids, true)) {
                    $this->getDb()->insert($this->pt('subscription_period_payment'), ['period_id' => $period->getId(), 'payment_id' => $payment->getId()]);
                    $ids[] = $payment->getId();
                }
            }
            $this->endTransaction();
        } catch (\Throwable $e) {
            $this->rollback();
            $period->setId($previousId);
            throw $e;
        }
    }

    /**
     * @return array<int,mixed>
     */
    public function getDueSubscriptions(\DateTimeInterface $now, int $afterId, int $limit = 100): array
    {
        $select = $this->getDb()->select('s.*')->from($this->pt('subscription'), 's')
            ->andWhere('s.id > :after_id')
            ->andWhere('s.status_id IN (:active, :failed)')
            ->andWhere('(s.next_billing_date <= :now OR s.end_date <= :now)')
            ->setParameter('after_id', $afterId)
            ->setParameter('active', Subscriptions::STATUS_ACTIVE)
            ->setParameter('failed', Subscriptions::STATUS_PAYMENT_FAILED)
            ->setParameter('now', $now->format('Y-m-d H:i:s'))
            ->orderBy('s.id', 'ASC')->setMaxResults($limit);
        return $this->getDb()->fetchAll($select);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getRenewalPeriod(int $subscriptionId, \DateTimeInterface $start): ?array
    {
        return $this->getDb()->fetchRow($this->getDb()->select('p.*')->from($this->pt('subscription_period'), 'p')
            ->where('subscription_id = :subscription_id AND start_date = :start_date')
            ->setParameter('subscription_id', $subscriptionId)
            ->setParameter('start_date', $start->format('Y-m-d H:i:s')));
    }

    /**
     * @return int[]
     */
    public function getPeriodPaymentIds(int $periodId): array
    {
        $rows = $this->getDb()->fetchAll($this->getDb()->select('payment_id')->from($this->pt('subscription_period_payment'))
            ->where('period_id = :period_id')->setParameter('period_id', $periodId));
        return array_map(static fn(array $row): int => (int)$row['payment_id'], $rows);
    }

    public function claimPeriodPayment(int $periodId, \DateTimeInterface $now): bool
    {
        if ($this->getDb()->getDoctrineConnection()->isTransactionActive()) {
            throw new \LogicException('Renewal payments must run outside an enclosing database transaction');
        }
        $periodTable = $this->pt('subscription_period');
        $subscriptionTable = $this->pt('subscription');
        return $this->getDb()->createQueryBuilder()->update($this->pt('subscription_period'))
                ->set('date_last_attempted', ':now')
                ->set('payment_error', ':error')
                ->where('id = :id AND date_last_attempted IS NULL')
                ->andWhere('EXISTS (SELECT 1 FROM ' . $subscriptionTable . ' s WHERE s.id = ' . $periodTable . '.subscription_id AND s.next_billing_date = ' . $periodTable . '.start_date AND s.next_billing_date <= :now AND s.status_id IN (:active, :failed) AND (s.end_date IS NULL OR s.end_date > :now))')
                ->setParameter('id', $periodId)
                ->setParameter('active', Subscriptions::STATUS_ACTIVE)
                ->setParameter('failed', Subscriptions::STATUS_PAYMENT_FAILED)
                ->setParameter('now', $now->format('Y-m-d H:i:s'))
                ->setParameter('error', 'Payment attempt started; reconcile before retrying if no payment is linked')
                ->executeStatement() === 1;
    }

    public function recordPeriodPaymentError(int $periodId, ?string $error): void
    {
        $this->getDb()->update($this->pt('subscription_period'), ['payment_error' => $error], ['id' => $periodId]);
    }

    public function saveRenewalSubscription(Subscription $subscription, \DateTimeInterface $expectedDate): bool
    {
        return $this->getDb()->createQueryBuilder()->update($this->pt('subscription'))
                ->set('next_billing_date', ':next_date')->set('status_id', ':status')->set('date_updated', ':updated')
                ->where('id = :id AND next_billing_date = :expected_date AND status_id IN (:active, :failed)')
                ->setParameter('id', $subscription->getId())
                ->setParameter('expected_date', $expectedDate->format('Y-m-d H:i:s'))
                ->setParameter('next_date', $subscription->getNextBillingDate()->format('Y-m-d H:i:s'))
                ->setParameter('status', $subscription->getStatus()->getId())
                ->setParameter('updated', $subscription->getDateUpdated()->format('Y-m-d H:i:s'))
                ->setParameter('active', Subscriptions::STATUS_ACTIVE)
                ->setParameter('failed', Subscriptions::STATUS_PAYMENT_FAILED)
                ->executeStatement() === 1;
    }
}
