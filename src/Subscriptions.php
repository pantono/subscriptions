<?php

namespace Pantono\Subscriptions;

use Pantono\Subscriptions\Repository\SubscriptionsRepository;
use Pantono\Hydrator\Hydrator;
use Pantono\Core\Events\EventHelper;
use Pantono\Subscriptions\Event\PreSubscriptionSaveEvent;
use Pantono\Subscriptions\Model\Subscription;
use Pantono\Subscriptions\Event\PostSubscriptionSaveEvent;
use Pantono\Subscriptions\Model\SubscriptionPeriod;
use Pantono\Subscriptions\Event\PreSubscriptionPeriodSaveEvent;
use Pantono\Subscriptions\Event\PostSubscriptionPeriodSaveEvent;
use Pantono\Subscriptions\Filter\SubscriptionFilter;
use Pantono\Subscriptions\Filter\SubscriptionPeriodFilter;
use Symfony\Component\Console\Output\OutputInterface;
use Pantono\Subscriptions\Model\SubscriptionStatus;
use Pantono\Payments\Payments;
use Pantono\Subscriptions\Model\SubscriptionPeriodItem;
use Pantono\Subscriptions\Model\SubscriptionTypeItem;
use Pantono\Subscriptions\Event\PreSubscriptionPeriodItemSaveEvent;
use Pantono\Subscriptions\Event\PostSubscriptionPeriodItemSaveEvent;

class Subscriptions
{
    public const int STATUS_ACTIVE = 1;
    public const int STATUS_PAYMENT_FAILED = 2;
    public const int STATUS_CANCELLED = 3;
    public const int STATUS_EXPIRED = 4;
    private SubscriptionsRepository $repository;
    private Hydrator $hydrator;
    private EventHelper $eventHelper;
    private Payments $payments;

    public function __construct(SubscriptionsRepository $repository, Hydrator $hydrator, EventHelper $eventHelper, Payments $payments)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->eventHelper = $eventHelper;
        $this->payments = $payments;
    }

    public function saveSubscription(Subscription $subscription): void
    {
        $previous = $subscription->getId() ? $this->hydrator->lookupRecord(Subscription::class, $subscription->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionSaveEvent::class, $subscription, $previous);

        $this->repository->saveModel($subscription);

        $this->eventHelper->fireEvent(PostSubscriptionSaveEvent::class, $subscription, $previous);
    }

    public function saveSubscriptionPeriod(SubscriptionPeriod $period): void
    {
        $previous = $period->getId() ? $this->hydrator->lookupRecord(Subscription::class, $period->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionPeriodSaveEvent::class, $period, $previous);

        $this->repository->saveSubscriptionPeriod($period);

        $this->eventHelper->fireEvent(PostSubscriptionPeriodSaveEvent::class, $period, $previous);
    }

    public function saveSubscriptionPeriodItem(SubscriptionPeriodItem $item): void
    {
        $previous = $item->getId() ? $this->hydrator->lookupRecord(SubscriptionPeriodItem::class, $item->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionPeriodItemSaveEvent::class, $item, $previous);

        $this->repository->saveModel($item);

        $this->eventHelper->fireEvent(PostSubscriptionPeriodItemSaveEvent::class, $item, $previous);
    }

    /**
     * @return Subscription[]
     */
    public function getSubscriptionsByFilter(SubscriptionFilter $filter): array
    {
        return $this->hydrator->hydrateSet(Subscription::class, $this->repository->getSubscriptionsByFilter($filter));
    }

    /**
     * @return SubscriptionPeriod[]
     */
    public function getSubscriptionPeriodsByFilter(SubscriptionPeriodFilter $filter): array
    {
        return $this->hydrator->hydrateSet(SubscriptionPeriod::class, $this->repository->getSubscriptionPeriodsByFilter($filter));
    }

    public function processNewSubscriptionPeriods(?OutputInterface $output = null): void
    {
        $filter = new SubscriptionFilter();
        $filter->setNextBillingDateStart(new \DateTime('00:00:00'));
        $filter->setNextBillingDateEnd(new \DateTime('23:59:59'));
        $filter->setStatus($this->hydrator->lookupRecord(SubscriptionStatus::class, self::STATUS_ACTIVE));
        foreach ($this->getSubscriptionsByFilter($filter) as $subscription) {
            if ($output) {
                $output->writeln('Processing subscription ' . $subscription->getId());
            }
            $newPeriod = $this->createNewPeriodForSubscription($subscription);
            if ($newPeriod->isPendingPaid() === false && count($newPeriod->getPayments()) > 0) {
                $status = $this->hydrator->lookupRecord(SubscriptionStatus::class, self::STATUS_PAYMENT_FAILED);
                $subscription->setStatus($status);
            }
            $subscription->setNextBillingDate($newPeriod->getEndDate());
            $this->saveSubscription($subscription);
        }
    }

    private function createNewPeriodForSubscription(Subscription $subscription): SubscriptionPeriod
    {
        $startDate = new \DateTime;
        $endDate = new \DateTime();
        $endDate->modify($subscription->getType()->getBillingIntervalCount() . ' ' . $subscription->getType()->getBillingIntervalUnit());
        $period = new SubscriptionPeriod();
        $period->setSubscription($subscription);
        $period->setStartDate($startDate);
        $period->setEndDate($endDate);
        $period->setPriceInPence($subscription->getPriceInPence());
        $mandate = $subscription->getPaymentMethod();
        if ($mandate) {
            $controller = $this->payments->getProviderController($mandate->getPaymentGateway());
            $payment = $controller->chargeMandate($mandate, $subscription->getPriceInPence(), $period->getPaymentDescriptor());
            $period->setPayments([$payment]);
        }
        $this->saveSubscriptionPeriod($period);
        return $period;
    }

    public function addItemToSubscriptionPeriod(SubscriptionPeriod $period, SubscriptionTypeItem $typeItem, int $quantity, array $metadata, ?\DateTimeInterface $date = null): SubscriptionPeriodItem
    {
        $item = new SubscriptionPeriodItem();
        $item->setDate($date ?? new \DateTime);
        $item->setMetadata($metadata);
        $item->setPeriodId($period->getId());
        $item->setType($typeItem);
        $item->setQuantity($quantity);
        $this->repository->saveModel($item);
        return $item;
    }
}
